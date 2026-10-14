<?php

namespace gftp\tests\unit;

use gftp\converter\SftpFileListConverter;
use gftp\FtpFile;
use PHPUnit\Framework\TestCase;

class SftpFileListConverterTest extends TestCase {

	private function entry(int $type, int $permissions, int $mtime = 0): array {
		return [
			'type' => $type,
			'mode' => $permissions,
			'uid' => 1000,
			'gid' => 100,
			'size' => 42,
			'mtime' => $mtime,
		];
	}

	public function testParseSkipsParentAndConvertsEntries(): void {
		$converter = new SftpFileListConverter();
		$files = $converter->parse([
			'..' => $this->entry(2, 040755),
			'b.txt' => $this->entry(1, 0100644, 0),
			'A' => $this->entry(2, 040750),
		], '/base');

		$this->assertCount(2, $files);
		$this->assertContainsOnlyInstancesOf(FtpFile::class, $files);

		$this->assertSame('/base/A', $files[0]->filename);
		$this->assertTrue($files[0]->isDir);
		$this->assertSame('0750', $files[0]->rights);

		$this->assertSame('/base/b.txt', $files[1]->filename);
		$this->assertFalse($files[1]->isDir);
		$this->assertSame('0644', $files[1]->rights);
		$this->assertSame(1000, $files[1]->user);
		$this->assertSame(100, $files[1]->group);
		$this->assertSame(42, $files[1]->size);
	}

	public function testParseCurrentDirectoryUsesBasePath(): void {
		$files = (new SftpFileListConverter())->parse(['.' => $this->entry(2, 040755)], '/base');

		$this->assertSame('/base', $files[0]->filename);
	}

	public function testParseRecursiveList(): void {
		$files = (new SftpFileListConverter())->parse([
			'sub' => [
				'.' => $this->entry(2, 040755),
				'inner.txt' => $this->entry(1, 0100600),
			],
		], '/base');

		$this->assertSame(['/base/sub', '/base/sub/inner.txt'], array_map(function (FtpFile $f) {
			return $f->filename;
		}, $files));
		$this->assertTrue($files[0]->isDir);
		$this->assertSame('0600', $files[1]->rights);
	}

	public function testParseUsesDateTimeFormat(): void {
		$converter = new SftpFileListConverter();
		$converter->setDateTimeFormat('Y-m-d');
		$mtime = (new \DateTime('2024-03-15 12:00:00'))->getTimestamp();

		$files = $converter->parse(['f' => $this->entry(1, 0100644, $mtime)]);

		$this->assertSame('2024-03-15', $files[0]->mdTime);
	}
}
