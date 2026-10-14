<?php

namespace gftp\tests\functional;

use gftp\drivers\SftpDriver;
use gftp\FtpException;
use gftp\FtpFile;
use PHPUnit\Framework\TestCase;

class SftpDriverFunctionalTest extends TestCase {

	/** @var SftpDriver */
	private $driver;

	/** @var string */
	private $dir;

	/** @var string[] */
	private $tmpFiles = [];

	protected function setUp(): void {
		if (getenv('SFTP_TEST_HOST') === false) {
			$this->markTestSkipped('SFTP_TEST_HOST not set (run "task test:functional").');
		}
		$this->driver = new SftpDriver($this->config());
		$this->dir = getenv('SFTP_TEST_DIR') . '/' . uniqid('t', true);
		$this->driver->mkdir($this->dir);
	}

	protected function tearDown(): void {
		if ($this->driver !== null) {
			try {
				$this->driver->rmdir($this->dir);
			} catch (FtpException $e) {
			}
			$this->driver->close();
		}
		foreach ($this->tmpFiles as $file) {
			@unlink($file);
		}
	}

	private function config(array $override = []): array {
		return array_merge([
			'host' => getenv('SFTP_TEST_HOST'),
			'port' => (int) getenv('SFTP_TEST_PORT'),
			'user' => getenv('SFTP_TEST_USER'),
			'pass' => getenv('SFTP_TEST_PASS'),
			'timeout' => 10,
		], $override);
	}

	private function tmpFile(string $content = ''): string {
		$file = tempnam(sys_get_temp_dir(), 'gsftp');
		file_put_contents($file, $content);
		$this->tmpFiles[] = $file;
		return $file;
	}

	private function serverFingerprint(): string {
		$probe = new \phpseclib3\Net\SFTP(getenv('SFTP_TEST_HOST'), (int) getenv('SFTP_TEST_PORT'));
		$fingerprint = SftpDriver::computeFingerprint($probe->getServerPublicHostKey());
		$probe->disconnect();
		return $fingerprint;
	}

	public function testConnectsWithMatchingFingerprint(): void {
		$driver = new SftpDriver($this->config(['hostKeyFingerprint' => $this->serverFingerprint()]));

		$this->assertTrue($driver->fileExists($this->dir));
		$driver->close();
	}

	public function testRefusesMismatchingFingerprint(): void {
		$driver = new SftpDriver($this->config([
			'hostKeyFingerprint' => 'SHA256:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
		]));

		$this->expectException(FtpException::class);
		$driver->pwd();
	}

	public function testUnreachableServerThrowsFtpException(): void {
		$driver = new SftpDriver($this->config(['port' => 1, 'timeout' => 2]));

		$this->expectException(FtpException::class);
		$driver->pwd();
	}

	public function testWrongPasswordFails(): void {
		$driver = new SftpDriver($this->config(['pass' => 'wrong-password']));

		$this->expectException(FtpException::class);
		$driver->pwd();
	}

	public function testFileLifecycle(): void {
		$local = $this->tmpFile('hello sftp');
		$remote = $this->dir . '/hello.txt';

		$this->driver->put($local, $remote);
		$this->assertTrue($this->driver->fileExists($remote));
		$this->assertSame(10, $this->driver->size($remote));
		$this->assertGreaterThan(0, $this->driver->mdtm($remote));

		$this->driver->chmod('0640', $remote);
		$files = $this->driver->ls($this->dir, true);
		$this->assertSame('0640', $this->findFile($files, $this->dir . '/hello.txt')->rights);

		$this->driver->rename($remote, $this->dir . '/renamed.txt');
		$this->assertFalse($this->driver->fileExists($remote));

		$download = $this->tmpFile();
		$this->driver->get($this->dir . '/renamed.txt', $download);
		$this->assertSame('hello sftp', file_get_contents($download));

		$this->driver->delete($this->dir . '/renamed.txt');
		$this->assertFalse($this->driver->fileExists($this->dir . '/renamed.txt'));
	}

	public function testChdirAndPwd(): void {
		$path = $this->driver->chdir($this->dir);

		$this->assertStringEndsWith($this->dir, $path);
		$this->assertSame($path, $this->driver->pwd());
	}

	/**
	 * @param FtpFile[] $files
	 */
	private function findFile(array $files, string $filename): FtpFile {
		foreach ($files as $file) {
			if ($file->filename === $filename) {
				return $file;
			}
		}
		$this->fail("File $filename not found");
	}
}
