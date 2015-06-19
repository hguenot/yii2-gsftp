<?php

namespace gftp\converter;

/**
 * Description of SftpFileListConverter
 */
class SftpFileListConverter extends \yii\base\Component implements FtpFileListConverter {
	
	private $dateTimeFormat = 'd/m/Y H:i:s';
	
	public function getDateTimeFormat() {
		return $this->dateTimeFormat;
	}

	public function setDateTimeFormat($dateTimeFormat) {
		$this->dateTimeFormat = $dateTimeFormat;
	}

		
	public function parse($fullList) {
		
		$ftpFiles = [];
		
		foreach ($fullList as $filename => $data) {
			$ftpFiles[] = new \gftp\FtpFile([
				'isDir' => $data['type'] === NET_SFTP_TYPE_DIRECTORY,
				'rights' => $this->_convertFilePermission($data['permissions']),
				'user' => $data['uid'],
				'group' => $data['gid'],
				'size' => $data['size'],
				'mdTime' => $this->_convertTime($data['mtime']),
				'filename' => $filename
			]);
		}
		
		return $ftpFiles;
	}
	
	private function _convertTime($time) {
		$dt = new \DateTime();
		$dt->setTimestamp($time);
		return $dt->format($this->dateTimeFormat);
	}
	
	private function _convertFilePermission($perms) {
		return '0' . (decoct ($perms) % 1000);
	}
	
}
