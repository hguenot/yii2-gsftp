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

		
	public function parse($fullList, $basePath = '') {
		
		$ftpFiles = [];

		foreach ($fullList as $filename => $data) {
			if ($filename == '..')
				continue;
			if (is_array($data) && !isset($data['type'])) {
				$ftpFiles = array_merge($ftpFiles, $this->parse($data, $basePath . '/' . $filename));
			} else {
				$path = $basePath . ($filename == '.' ? '' : ('/' . $filename));
				if (is_object($data)) {
					$data = (array) $data;
				} 
				$ftpFiles[] = new \gftp\FtpFile([
					'isDir' => $data['type'] === NET_SFTP_TYPE_DIRECTORY,
					'rights' => $this->_convertFilePermission($data['permissions']),
					'user' => \yii\helpers\ArrayHelper::getValue($data, 'uid'),
					'group' => \yii\helpers\ArrayHelper::getValue($data, 'gid'),
					'size' => \yii\helpers\ArrayHelper::getValue($data, 'size'),
					'mdTime' => $this->_convertTime($data['mtime']),
					'filename' => $path
				]);
			}
		}

		usort($ftpFiles, function($ftpFile1, $ftpFile2){
			return strcmp(strtolower($ftpFile1->filename), strtolower($ftpFile2->filename));
		});
		
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
