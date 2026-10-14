<?php

namespace gftp\tests\unit;

use gftp\drivers\SftpDriver;
use gftp\FtpException;
use phpseclib3\Crypt\Common\PrivateKey;
use phpseclib3\Crypt\EC;
use phpseclib3\Exception\ConnectionClosedException;
use phpseclib3\Exception\TimeoutException;
use phpseclib3\Net\SFTP;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SftpDriverTest extends TestCase {

	private const HOST_KEY = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIF15HXIW72FVlox28w8o9/LuV/DRijmbxeNBuStBn2bK';
	private const HOST_KEY_FINGERPRINT = 'SHA256:VgNzPbL4zQA8eJqcftwoTRc5Z+NGmyf4s/+mKyqKzXQ';

	/** @var SFTP|MockObject */
	private $handle;

	/** @var string[] */
	private $tmpFiles = [];

	protected function setUp(): void {
		$this->handle = $this->createMock(SFTP::class);
	}

	protected function tearDown(): void {
		foreach ($this->tmpFiles as $file) {
			@unlink($file);
		}
	}

	private function createDriver(array $config = []): SftpDriver {
		$handle = $this->handle;
		return new class($handle, $config) extends SftpDriver {
			private $mockHandle;

			public function __construct(SFTP $mockHandle, array $config) {
				$this->mockHandle = $mockHandle;
				parent::__construct($config);
			}

			protected function createHandle(): SFTP {
				return $this->mockHandle;
			}
		};
	}

	private function writeKeyFile(string $content): string {
		$file = tempnam(sys_get_temp_dir(), 'gsftp');
		file_put_contents($file, $content);
		$this->tmpFiles[] = $file;
		return $file;
	}

	public function testComputeFingerprintMatchesSshKeygen(): void {
		$this->assertSame(self::HOST_KEY_FINGERPRINT, SftpDriver::computeFingerprint(self::HOST_KEY));
	}

	public function testHostKeyNotCheckedWithoutFingerprint(): void {
		$this->handle->expects($this->never())->method('getServerPublicHostKey');

		$this->createDriver()->connect();
	}

	/**
	 * @dataProvider validFingerprintProvider
	 */
	public function testHostKeyAcceptedWhenFingerprintMatches(string $fingerprint): void {
		$this->handle->method('getServerPublicHostKey')->willReturn(self::HOST_KEY);
		$this->handle->expects($this->never())->method('disconnect');

		$this->createDriver(['hostKeyFingerprint' => $fingerprint])->connect();
	}

	public function validFingerprintProvider(): array {
		return [
			'ssh-keygen format' => [self::HOST_KEY_FINGERPRINT],
			'without prefix' => [substr(self::HOST_KEY_FINGERPRINT, 7)],
			'with padding' => [self::HOST_KEY_FINGERPRINT . '='],
			'lowercase prefix and spaces' => ['  sha256:' . substr(self::HOST_KEY_FINGERPRINT, 7) . ' '],
		];
	}

	public function testHostKeyRejectedWhenFingerprintDiffers(): void {
		$this->handle->method('getServerPublicHostKey')->willReturn(self::HOST_KEY);
		$this->handle->expects($this->once())->method('disconnect');

		$this->expectException(FtpException::class);
		$this->createDriver(['hostKeyFingerprint' => 'SHA256:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'])->connect();
	}

	public function testHostKeyRejectedWhenServerKeyUnavailable(): void {
		$this->handle->method('getServerPublicHostKey')->willReturn(false);

		$this->expectException(FtpException::class);
		$this->createDriver(['hostKeyFingerprint' => self::HOST_KEY_FINGERPRINT])->connect();
	}

	public function testHostKeyVerifiedBeforeLogin(): void {
		$this->handle->method('getServerPublicHostKey')->willReturn(self::HOST_KEY);
		$this->handle->expects($this->never())->method('login');

		$driver = $this->createDriver([
			'hostKeyFingerprint' => 'SHA256:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
			'user' => 'user',
			'pass' => 'secret',
		]);

		$this->expectException(FtpException::class);
		$driver->pwd();
	}

	public function testLoginWithPassword(): void {
		$this->handle->expects($this->once())->method('login')->with('user', 'secret')->willReturn(true);

		$this->createDriver(['user' => 'user', 'pass' => 'secret'])->login();
	}

	public function testLoginWithPasswordFailure(): void {
		$this->handle->method('login')->willReturn(false);

		$this->expectException(FtpException::class);
		$this->createDriver(['user' => 'user', 'pass' => 'wrong'])->login();
	}

	public function testLoginWithoutUserFails(): void {
		$this->expectException(FtpException::class);
		$this->createDriver()->login();
	}

	public function testLoginWithoutCredentialsFails(): void {
		$this->handle->expects($this->never())->method('login');

		$this->expectException(FtpException::class);
		$this->createDriver(['user' => 'user'])->login();
	}

	public function testRuntimeErrorDuringLoginIsWrapped(): void {
		$timeout = new TimeoutException('Timed out');
		$this->handle->method('login')->willThrowException($timeout);

		try {
			$this->createDriver(['user' => 'user', 'pass' => 'secret'])->login();
			$this->fail('FtpException expected');
		} catch (FtpException $e) {
			$this->assertSame($timeout, $e->getPrevious());
		}
	}

	public function testRuntimeErrorIsWrappedAndForcesReconnection(): void {
		$this->handle->expects($this->exactly(2))->method('login')->willReturn(true);
		$this->handle->method('pwd')->willReturnOnConsecutiveCalls(
			$this->throwException(new ConnectionClosedException('Connection closed by server')),
			'/home/user'
		);
		$driver = $this->createDriver(['user' => 'user', 'pass' => 'secret']);

		try {
			$driver->pwd();
			$this->fail('FtpException expected');
		} catch (FtpException $e) {
			$this->assertInstanceOf(ConnectionClosedException::class, $e->getPrevious());
		}

		$this->assertSame('/home/user', $driver->pwd());
	}

	public function testLoginWithEncryptedPrivateKey(): void {
		$key = EC::createKey('Ed25519')->withPassword('passphrase');
		$keyFile = $this->writeKeyFile($key->toString('OpenSSH'));

		$this->handle->expects($this->once())
			->method('login')
			->with('user', $this->isInstanceOf(PrivateKey::class))
			->willReturn(true);

		$this->createDriver(['user' => 'user', 'pass' => 'passphrase', 'privateKeyFile' => $keyFile])->login();
	}

	public function testLoginWithWrongPassphraseFails(): void {
		$key = EC::createKey('Ed25519')->withPassword('passphrase');
		$keyFile = $this->writeKeyFile($key->toString('OpenSSH'));
		$this->handle->expects($this->never())->method('login');

		$this->expectException(FtpException::class);
		$this->createDriver(['user' => 'user', 'pass' => 'wrong', 'privateKeyFile' => $keyFile])->login();
	}

	public function testLoginWithMissingKeyFileFails(): void {
		$this->expectException(FtpException::class);
		$this->createDriver(['user' => 'user', 'privateKeyFile' => '/nonexistent/key'])->login();
	}

	public function testLoginWithPrivateKeyRejected(): void {
		$keyFile = $this->writeKeyFile(EC::createKey('Ed25519')->toString('OpenSSH'));
		$this->handle->method('login')->willReturn(false);

		$this->expectException(FtpException::class);
		$this->createDriver(['user' => 'user', 'privateKeyFile' => $keyFile])->login();
	}

	/**
	 * @dataProvider chmodModeProvider
	 */
	public function testChmodConvertsOctalString(string $mode, int $expected): void {
		$this->handle->method('login')->willReturn(true);
		$this->handle->expects($this->once())->method('chmod')->with($expected, 'file.txt', false)->willReturn($expected);

		$this->createDriver(['user' => 'user', 'pass' => 'secret'])->chmod($mode, 'file.txt');
	}

	public function chmodModeProvider(): array {
		return [
			'without leading zero' => ['755', 0755],
			'with leading zero' => ['0644', 0644],
			'short' => ['600', 0600],
		];
	}

	public function testChmodToZeroModeIsNotAFailure(): void {
		$this->handle->method('login')->willReturn(true);
		$this->handle->method('chmod')->willReturn(0);

		$this->createDriver(['user' => 'user', 'pass' => 'secret'])->chmod('000', 'file.txt');
		$this->addToAssertionCount(1);
	}

	public function testChmodFailure(): void {
		$this->handle->method('login')->willReturn(true);
		$this->handle->method('chmod')->willReturn(false);

		$this->expectException(FtpException::class);
		$this->createDriver(['user' => 'user', 'pass' => 'secret'])->chmod('644', 'file.txt');
	}

	public function testSize(): void {
		$this->handle->method('login')->willReturn(true);
		$this->handle->expects($this->once())->method('filesize')->with('file.txt')->willReturn(10);

		$this->assertSame(10, $this->createDriver(['user' => 'user', 'pass' => 'secret'])->size('file.txt'));
	}

	public function testSizeFailure(): void {
		$this->handle->method('login')->willReturn(true);
		$this->handle->method('filesize')->willReturn(false);

		$this->expectException(FtpException::class);
		$this->createDriver(['user' => 'user', 'pass' => 'secret'])->size('missing.txt');
	}

	public function testTranslationFolderIsRegistered(): void {
		$this->createDriver();

		$source = \Yii::$app->getI18n()->translations['gsftp'];
		$basePath = is_array($source) ? $source['basePath'] : \Yii::getAlias($source->basePath);
		$this->assertDirectoryExists($basePath);
		$this->assertSame(realpath(dirname(__DIR__, 2) . '/messages'), realpath($basePath));
	}

	public function testNullableGetters(): void {
		$driver = $this->createDriver();

		$this->assertNull($driver->getUser());
		$this->assertNull($driver->getPublicKeyFile());
		$this->assertNull($driver->getHostKeyFingerprint());
	}

	public function testChangingFingerprintClosesConnection(): void {
		$this->handle->expects($this->once())->method('disconnect');
		$driver = $this->createDriver();
		$driver->connect();

		$driver->setHostKeyFingerprint(self::HOST_KEY_FINGERPRINT);

		$this->assertSame(self::HOST_KEY_FINGERPRINT, $driver->getHostKeyFingerprint());
	}
}
