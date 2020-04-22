<?php

namespace gftp\drivers;

use gftp\converter\FtpFileListConverter;
use \gftp\FtpException;
use \gftp\converter\SftpFileListConverter;
use \gftp\converter\SimpleFileListConverter;
use gftp\FtpUtils;
use \phpseclib\Net\SFTP;
use \phpseclib\Crypt\RSA;
use \Yii;
use yii\base\BaseObject;

/**
 * SFTP (SSH) connection driver.
 *
 * @property string $host
 * @property int $port
 * @property string|null $user
 * @property-write string|null $password
 * @property int $timeout
 * @property string|null $publicKeyFile
 * @property-write string|null $privateKeyFile
 * @property FtpFileListConverter $fileListConverter
 */
class SftpDriver extends BaseObject implements RemoteDriver {
	
	/**
	 * @var SFTP SFTP Handle
	 */
	private $_handle = null;
	
	/**
	 * @var string SSH host name or IP.
	 */
	private $_host = 'localhost';
	
	/**
	 * @var int SSH port.
	 */
	private $_port = 22;
	
	/**
	 * @var int SSH connection timeout
	 */
	private $_timeout = 10;
	
	/**
	 * @var string|null Username used for SSH user authentication.
	 */
	private $_user = null;
	
	/**
	 * @var string|null Password for SSH user/password authentication or passphrase for public/private key authentification.
	 */
	private $_pass = null;
	
	/**
	 * @var string|null Public key filename for public/private key authentication
	 */
	private $_publicKeyFile = null;
	
	/**
	 * @var string|null Private key filename for public/private key authentication
	 */
	private $_privateKeyFile = null;
	/**
	 * @var FtpFileListConverter File list converter.
	 */
	private $_fileListConverter = null;

	/**
	 * @inheritDoc
	 */
	public function init() {
		parent::init();
		$this->_fileListConverter = new SftpFileListConverter();
		FtpUtils::registerTranslationFolder('gsftp', __DIR__ . '/messages');
	}

	/**
	 * @return string The current connected host.
	 */
	public function getHost(): string {
		return $this->_host;
	}

	/**
	 * @param string $host Change the connected host
	 *
	 * @throws FtpException If closing current connection failed
	 */
	public function setHost(string $host): void {
		// Close connection before changing host.
		if ($this->_host !== $host) {
			$this->close();
			$this->_host = $host;
		}
	}

	/**
	 * @return int The current connected port
	 */
	function getPort(): int {
		return $this->_port;
	}

	/**
	 * @param int $port The new port to connect
	 *
	 * @throws FtpException If closing current connection failed
	 */
	function setPort(int $port): void {
		// Close connection before changing poirt.
		if ($this->_port !== $port) {
			$this->close();
			$this->_port = $port;
		}
	}

	/**
	 * Changing FTP connecting username.
	 *
	 * @param string $user New username
	 *
	 * @throws FtpException If closing current connection failed
	 */
	public function setUser(string $user): void {
		// Close connection before changing username.
		if ($this->_user !== $user) {
			$this->close();
			$this->_user = $user;
		}
	}

	/**
	 * @return string The FTP connecting username.
	 */
	public function getUser(): string {
		return $this->_user;
	}

	/**
	 * Changing FTP password.
	 *
	 * @param string|null $pass New password
	 *
	 * @throws FtpException If closing current connection failed
	 */
	public function setPass(?string $pass): void {
		// Close connection before changing password.
		if ($this->_pass !== $pass) {
			$this->close();
			$this->_pass = $pass;
		}
	}

	/**
	 * @return string The current public key file
	 */
	public function getPublicKeyFile(): string {
		return $this->_publicKeyFile;
	}

	/**
	 * @param string|null $publicKeyFile The new public key file
	 *
	 * @throws FtpException If closing current connection failed
	 */
	public function setPublicKeyFile(?string $publicKeyFile) {
		// Close connection before changing public key.
		if ($this->_publicKeyFile !== $publicKeyFile) {
			$this->close();
			$this->_publicKeyFile = $publicKeyFile;
		}
	}

	/**
	 * @param string|null $privateKeyFile The new private key file
	 *
	 * @throws FtpException If closing current connection failed
	 */
	public function setPrivateKeyFile(?string $privateKeyFile) {
		// Close connection before changing private key.
		if ($this->_privateKeyFile !== $privateKeyFile) {
			$this->close();
			$this->_privateKeyFile = $privateKeyFile;
		}
	}

	/**
	 * Changing connection timeout in seconds.
	 *
	 * @param integer $timeout Set passive mode
	 */
	public function setTimeout(int $timeout): void {
		$this->_timeout = $timeout;
	}

	/**
	 * @return integer FTP connection timeout.
	 */
	public function getTimeout(): int {
		return $this->_timeout;
	}

	/**
	 * @inheritDoc
	 */
	public function connect(): void {
		$this->_handle = new SFTP($this->_host, $this->_port);
	}

	/**
	 * @inheritDoc
	 */
	public function login(): void {
		$this->connectIfNeeded(false);
		if ($this->_user === null) {
			throw new FtpException(
				Yii::t('gsftp', 'Could not login to SFTP server "{host}" on port "{port}" without username.', [
						'host' => $this->_host, 'port' => $this->_port
				])
			);
		} else {
			if ($this->_privateKeyFile != null) {
				$key = new RSA();
				if ($this->_pass != null && !empty($this->_pass))
					$key->setPassword ($this->_pass);
					
				if ($this->_publicKeyFile != null && !empty($this->_publicKeyFile))
					$key->setPublicKey(self::_readKeyFile('Public', $this->_publicKeyFile));
				
				$key->setPrivateKey(self::_readKeyFile('Private', $this->_privateKeyFile));
				
				if (!$this->_handle->login($this->_user, $key)) {
					throw new FtpException(
						Yii::t('gsftp', 'Could not login to SFTP server "{host}" on port "{port}" with user "{user}" using RSA key.', [
								'host' => $this->_host, 'port' => $this->_port, 'user' => $this->_user
						])
					);
				}
			} else if ($this->_pass != null && !empty($this->_pass)) {
				if (!$this->_handle->login($this->_user, $this->_pass)) {
					throw new FtpException(
						Yii::t('gsftp', 'Could not login to SFTP server "{host}" on port "{port}" with user "{user}".', [
								'host' => $this->_host, 'port' => $this->_port, 'user' => $this->_user
						])
					);
				}
			}
		}
	}

	/**
	 * @param string $keyType 'Public' or 'Private' key file
	 * @param string $keyFile Key file to read
	 *
	 * @return string File content
	 *
	 * @throws FtpException If key file could not be read.
	 */
	private static function _readKeyFile($keyType, string $keyFile): string {
		if (!file_exists($keyFile)) {
			throw new FtpException(
				Yii::t('gsftp', '{keyType} key file "{keyFile}" does not exists.', [
					'keyType' => $keyType, 'keyFile' => $keyFile
				])
			);
		}
		$key = file_get_contents($keyFile);
		if ($key === false) {
			throw new FtpException(
				Yii::t('gsftp', '{keyType} key file "{keyFile}" could not be read.', [
					'keyType' => $keyType, 'keyFile' => $keyFile
				])
			);
		}
		return $key;
	}

	/**
	 * @inheritDoc
	 */
	public function close(): void {
		if ($this->_handle !== null) {
			$this->_handle->disconnect();
			$this->_handle = null;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function pwd(): string {
		$this->connectIfNeeded();
		return $this->_handle->pwd();
	}

	/**
	 * @inheritDoc
	 */
	public function chdir(string $path): string {
		$this->connectIfNeeded();
		if (!$this->_handle->chdir($path)) {
			throw new FtpException(
				Yii::t('gsftp', 'Could not go to "{folder}" on server "{host}"', [
						'host' => $this->_host, 'folder' => $path
				])
			);
		}
		return $this->pwd();
	}

	/**
	 * @inheritDoc
	 */
	public function ls(string $dir = '.', bool $full = false, bool $recursive = false): array {
		$this->connectIfNeeded();
		$fileListConverter = $this->_fileListConverter;
		
		if ($full) {
			$files = $this->_handle->rawlist($dir, $recursive);
		} else {
			$files = $this->_handle->nlist($dir, $recursive);
			$fileListConverter = new SimpleFileListConverter();
		}
		
		if ($files === false) {
			throw new FtpException(
				Yii::t('gsftp', 'Could not read folder "{folder}" on server "{host}"', [
						'host' => $this->_host, 'folder' => $dir
				])
			);
		}

		return $fileListConverter->parse($files, $dir);
	}

	/**
	 * @inheritDoc
	 */
	public function mdtm(string $path): int {
		$this->connectIfNeeded();

		$file = $this->_handle->stat($path);
		
		if ($file === false) {
			throw new FtpException(
				Yii::t('gsftp', 'Could not get modification time of file "{file}" on server "{host}"', [
						'host' => $this->_host, 'file' => $path
				])
			);
		}
		
		return $file['mtime'];
	}

	/**
	 * @inheritDoc
	 */
	public function mkdir(string $dir): void {
		$this->connectIfNeeded();

		if (!$this->_handle->mkdir($dir)) {
			throw new FtpException(
				Yii::t('gsftp', 'An error occured while creating folder "{folder}" on server "{host}"', [
						'host' => $this->_host, 'folder' => $dir
				])
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function rmdir(string $dir): void {
		$this->delete($dir, true);
	}

	/**
	 * @inheritDoc
	 */
	public function chmod(string $mode, string $file, bool $recursive = false): void {
		$this->connectIfNeeded();
		if (substr($mode, 0, 1) != '0') {
			$mode = (int) (octdec ( str_pad ( $mode, 4, '0', STR_PAD_LEFT ) ));
		}

		if (!$this->_handle->chmod($mode, $file, $recursive)) {
			throw new FtpException(
				Yii::t('gsftp', 'Could change mode (to "{mode}") of file "{file}" on server "{host}"', [
						'host' => $this->_host, 'file' => $file, '{mode}' => $mode
				])
			);
		}
		
	}

	/**
	 * @inheritDoc
	 */
	public function fileExists(string $filename): bool {
		$this->connectIfNeeded();
		return $this->_handle->file_exists($filename);
	}

	/**
	 * @inheritDoc
	 */
	public function delete(string $path, bool $recursive = false): void {
		$this->connectIfNeeded();
		if (!$this->_handle->delete($path, $recursive)) {
			throw new FtpException(
				Yii::t('gsftp', 'Could not delete file "{file}" on server "{host}"', [
						'host' => $this->_host, 'file' => $path
				])
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function rename(string $oldName, string $newName): void {
		$this->connectIfNeeded();
		if (!$this->_handle->rename($oldName, $newName)) {
			throw new FtpException(
				Yii::t('gsftp', 'Could not rename file "{oldname}" to "{newname}" on server "{host}"',[
						'host' => $this->_host, 'oldname' => $oldName, 'newname' => $newName
				])
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function size(string $path): int {
		$this->connectIfNeeded();

		$res = $this->_handle->size($path);
		if ($res === false) {
			throw new FtpException(
				Yii::t('gsftp', 'Could not get size of file "{file}" on server "{host}"', [
						'host' => $this->_host, 'file' => $path
				])
			);
		}
		
		return $res;
	}

	/**
	 * @inheritDoc
	 */
	public function get(
			string $remote_file,
			$local_file = null,
			int $mode = FTP_ASCII,
			bool $asynchronous = false,
			callable $asyncFn = null): void {
		$this->connectIfNeeded();

		if (!isset($local_file) || $local_file == null || !is_string($local_file) || trim($local_file) == "") {
			$local_file = getcwd() . DIRECTORY_SEPARATOR . basename($remote_file);
		}
		
		if (!$this->_handle->get($remote_file, $local_file)){
			throw new FtpException(
				Yii::t('gsftp', 'Could not synchronously get file "{remote_file}" from server "{host}"', [
						'host' => $this->_host, 'remote_file' => $remote_file
				])
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function put(
			$local_file,
			?string $remote_file = null,
			int $mode = FTP_ASCII,
			bool $asynchronous = false,
			callable $asyncFn = null): void {
		$this->connectIfNeeded();
		if (!isset($remote_file) || $remote_file == null || !is_string($remote_file) || trim($remote_file) == "") {
			$remote_file = basename($local_file);
		}

		if (!$this->_handle->put($remote_file, $local_file, SFTP::SOURCE_LOCAL_FILE)) {
			throw new FtpException(
				Yii::t('gsftp', 'Could not put file "{local_file}" on "{remote_file}" on server "{host}"', [
						'host' => $this->_host, 'remote_file' => $remote_file, 'local_file' => $local_file
				])
			);
		}
	}

	/**
	 * @param bool $withLogin
	 *
	 * @throws FtpException If connection or login failed.
	 */
	private function connectIfNeeded(bool $withLogin = true): void {
		if ($this->_handle == null) {
			$this->connect();
		
			if ($withLogin) {
				$this->login();
			}
		}
	}
	
}
