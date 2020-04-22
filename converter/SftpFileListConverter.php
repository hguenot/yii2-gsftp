<?php

namespace gftp\converter;

use gftp\FtpFile;
use yii\base\Component;
use yii\helpers\ArrayHelper;

/**
 * Description of SftpFileListConverter
 */
class SftpFileListConverter extends Component implements FtpFileListConverter {

	/** @var string Date time format returns by the SFTP server */
	private $dateTimeFormat = 'd/m/Y H:i:s';
	
	public function getDateTimeFormat(): string {
		return $this->dateTimeFormat;
	}

	public function setDateTimeFormat(string $dateTimeFormat): void {
		$this->dateTimeFormat = $dateTimeFormat;
	}

	/**
	 * @param string[] $fullList String array returned by ftp_rawlist.
	 * @param string $basePath Base path of file
	 *
	 * @return FtpFile[] Converted file list.
	 */
	public function parse(array $fullList, $basePath = ''): array {
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
					'user' => ArrayHelper::getValue($data, 'uid'),
					'group' => ArrayHelper::getValue($data, 'gid'),
					'size' => ArrayHelper::getValue($data, 'size'),
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
