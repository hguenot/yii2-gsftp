<?php

namespace gftp\drivers;

use \gftp\FtpException;
use \gftp\converter\SftpFileListConverter;
use \gftp\converter\SimpleFileListConverter;
use \phpseclib\Net\SFTP;
use \phpseclib\Crypt\RSA;
use \Yii;

/**
 * SFTP (SSH) connection driver.
 */
class SftpDriver extends \yii\base\Object implements RemoteDriver {
	
	/**
	 * @var \phpseclib\Net\SFTP SFTP Handle 
	 */
	private $handle = null;
	
	/**
	 * @var string SSH host name or IP.
	 */
	private $host = 'localhost';
	
	/**
	 * @var int SSH port.
	 */
	private $port = 22;
	
	/**
	 * @var int SSH connection timeout
	 */
	private $timeout = 10;
	
	/**
	 * @var string Username used for SSH user authentication.
	 */
	private $user = null;
	
	/**
	 * @var string Password for SSH user/password authentication or passphrase for public/private key authentification.
	 */
	private $pass = null;
	
	/**
	 * @var string Public key filename for public/private key authentification
	 */
	private $publicKeyFile = null;
	
	/**
	 * @var string Private key filename for public/private key authentification
	 */
	private $privateKeyFile = null;
	
	private $fileListConverter = null;
	
	public function init() {
		parent::init();
		$this->fileListConverter = new SftpFileListConverter();
		\gftp\FtpUtils::registerTranslationFolder('gsftp', __DIR__ . '/messages');
	}
	
	public function getHost() {
		return $this->host;
	}
	
	public function setHost(/* string */ $host) {
		$this->host = $host;
	}
	
	function getPort() {
		return $this->port;
	}

	function setPort(/* int */ $port) {
		$this->port = $port;
	}

	public function getTimeout() {
		return $this->timeout;
	}

	public function setTimeout(/* int */ $timeout) {
		$this->timeout = $timeout;
		return $this;
	}

	function getUser() {
		return $this->user;
	}

	function setUser(/* string */ $user) {
		$this->user = $user;
	}

	function getPass() {
		return $this->pass;
	}

	function setPass(/* string */ $pass) {
		$this->pass = $pass;
	}

	public function getPublicKeyFile() {
		return $this->publicKeyFile;
	}

	public function setPublicKeyFile(/* string */ $publicKeyFile) {
		$this->publicKeyFile = $publicKeyFile;
	}

	public function getPrivateKeyFile() {
		return $this->privateKeyFile;
	}

	public function setPrivateKeyFile(/* string */ $privateKeyFile) {
		$this->privateKeyFile = $privateKeyFile;
	}

	
					
	public function connect() {
		$this->handle = new SFTP($this->host, $this->port);
	}

	public function login() {
		$this->connectIfNeeded(false);
		if ($this->user === null) {
			throw new FtpException(
				Yii::t('gsftp', 'Could not login to SFTP server "{host}" on port "{port}" without username.', [
					'host' => $this->host, 'port' => $this->port
				])
			);
		} else {
			if ($this->privateKeyFile != null) {
				$key = new RSA();
				if ($this->pass != null && !empty($this->pass)) 
					$key->setPassword ($this->pass);
					
				if ($this->publicKeyFile != null && !empty($this->publicKeyFile)) 
					$key->setPublicKey(self::_readKeyFile('Public', $this->publicKeyFile));
				
				$key->setPrivateKey(self::_readKeyFile('Private', $this->privateKeyFile));
				
				if (!$this->handle->login($this->user, $key)) {
					throw new FtpException(
						Yii::t('gsftp', 'Could not login to SFTP server "{host}" on port "{port}" with user "{user}" using RSA key.', [
							'host' => $this->host, 'port' => $this->port, 'user' => $this->user
						])
					);
				}
			} else if ($this->pass != null && !empty($this->pass)) {
				if (!$this->handle->login($this->user, $this->pass)) {
					throw new FtpException(
						Yii::t('gsftp', 'Could not login to SFTP server "{host}" on port "{port}" with user "{user}".', [
							'host' => $this->host, 'port' => $this->port, 'user' => $this->user
						])
					);
				}
			}
		}
	}
	
	private static function _readKeyFile($keyType, $keyFile) {
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

	public function close() {
		if ($this->handle !== null) {
			$this->handle->disconnect();
			$this->handle = null;
		}
	}
	
	public function pwd() {
		$this->connectIfNeeded();
		return $this->handle->pwd();
	}

	public function chdir($path) {
		$this->connectIfNeeded();
		if (!$this->handle->chdir($path)) {
			throw new FtpException(
				Yii::t('gsftp', 'Could not go to "{folder}" on server "{host}"', [
					'host' => $this->host, 'folder' => $path
				])
			);
		}
	}
	
	public function ls($dir = ".", $full = false, $recursive = false) {
		$this->connectIfNeeded();
		$fileListConverter = $this->fileListConverter;
		
		if ($full) {
			$files = $this->handle->rawlist($dir, $recursive);
		} else {
			$files = $this->handle->nlist($dir, $recursive);
			$fileListConverter = new SimpleFileListConverter();
		}
		
		if ($files === false) {
			throw new FtpException(
				Yii::t('gsftp', 'Could not read folder "{folder}" on server "{host}"', [
					'host' => $this->host, 'folder' => $dir
				])
			);
		}

		return $fileListConverter->parse($files, $dir);
	}

	public function mdtm($path) {
		$this->connectIfNeeded();

		$file = $this->handle->stat($path);
		
		if ($file === false) {
			throw new FtpException(
				Yii::t('gsftp', 'Could not get modification time of file "{file}" on server "{host}"', [
					'host' => $this->host, 'file' => $path
				])
			);
		}
		
		return $file['mtime'];
	}

	public function mkdir($dir) {
		$this->connectIfNeeded();

		if (!$this->handle->mkdir($dir)) {
			throw new FtpException(
				Yii::t('gsftp', 'An error occured while creating folder "{folder}" on server "{host}"', [
					'host' => $this->host, 'folder' => $dir
				])
			);
		}
	}

	public function rmdir($dir) {
		$this->delete($dir, true);
	}


	public function chmod($mode, $file, $recursive = false) {
		$this->connectIfNeeded();
		if (substr($mode, 0, 1) != '0') {
			$mode = (int) (octdec ( str_pad ( $mode, 4, '0', STR_PAD_LEFT ) ));
		}

		if (!$this->handle->chmod($mode, $file, $recursive)) {
			throw new FtpException(
				Yii::t('gsftp', 'Could change mode (to "{mode}") of file "{file}" on server "{host}"', [
					'host' => $this->host, 'file' => $file, '{mode}' => $mode
				])
			);
		}
		
	}

	public function fileExists($filename) {
		$this->connectIfNeeded();
		return $this->handle->file_exists($filename);
	}

	public function delete($path, $recursive = false) {
		$this->connectIfNeeded();
		if (!$this->handle->delete($path, $recursive)) {
			throw new FtpException(
				Yii::t('gsftp', 'Could not delete file "{file}" on server "{host}"', [
					'host' => $this->host, 'file' => $path
				])
			);
		}
	}

	public function rename($oldname, $newname) {
		$this->connectIfNeeded();
		if (!$this->handle->rename($oldname, $newname)) {
			throw new FtpException(
				Yii::t('gsftp', 'Could not rename file "{oldname}" to "{newname}" on server "{host}"',[
					'host' => $this->host, 'oldname' => $oldname, 'newname' => $newname
				])
			);
		}
	}

	public function size($path) {
		$this->connectIfNeeded();

		$res = $this->handle->size($path);
		if ($res === false) {
			throw new FtpException(
				Yii::t('gsftp', 'Could not get size of file "{file}" on server "{host}"', [
					'host' => $this->host, 'file' => $path
				])
			);
		}
		
		return $res;
	}

	public function get($remote_file, $local_file = null, $mode = FTP_ASCII, $asynchronous = false, callable $asyncFn = null) {
		$this->connectIfNeeded();

		if (!isset($local_file) || $local_file == null || !is_string($local_file) || trim($local_file) == "") {
			$local_file = getcwd() . DIRECTORY_SEPARATOR . basename($remote_file);
		}
		
		if (!$this->handle->get($remote_file, $local_file)){
			throw new FtpException(
				Yii::t('gsftp', 'Could not synchronously get file "{remote_file}" from server "{host}"', [
					'host' => $this->host, 'remote_file' => $remote_file
				])
			);
		}
		
		return realpath($local_file);
	}

	public function put($local_file, $remote_file = null, $mode = FTP_ASCII, $asynchronous = false, callable $asyncFn = null) {
		$this->connectIfNeeded();
		if (!isset($remote_file) || $remote_file == null || !is_string($remote_file) || trim($remote_file) == "") {
			$remote_file = basename($local_file);
		}

		if (!$this->handle->put($remote_file, $local_file, SFTP::SOURCE_LOCAL_FILE)) {
			throw new FtpException(
				Yii::t('gsftp', 'Could not put file "{local_file}" on "{remote_file}" on server "{host}"', [
					'host' => $this->host, 'remote_file' => $remote_file, 'local_file' => $local_file
				])
			);
		}
		
		return $remote_file;
	}

	private function connectIfNeeded($withLogin = true) {
		if ($this->handle == null) {
			$this->connect();
		
			if ($withLogin) {
				$this->login();
			}
		}
	}
	
}
