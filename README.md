SFtp
====
SFtp is a FTP extension for [YII 2 Framework](http://www.yiiframework.com) based on 
[Yii2-gftp](https://github.com/hguenot/yii2-gftp) extension.

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist hguenot/yii2-gsftp "*"
```

or add

```
"hguenot/yii2-gsftp": "*"
```

to the require section of your `composer.json` file.


Usage
-----

Here is a basic usage of GFtp extension. 

* Create an FTP application component (in your Web config file)

```php
return [
	// [...]
	'components'=>[
		// [...]
		'ftp' => [
			'class' => '\gftp\FtpComponent',
			'connectionString' => 'sftp://user:pass@host:22',
			'driverOptions' => [ 'timeout' => 120 ]
		]
	],
	// [...]
];
```

* You can user either a connection string where protocol could be ftp or ftps or directly set `protocol`, `user`, 
  `pass`, `host` and `port` properties :  

```php
return [
	// [...]
	'components'=>[
		// [...]
		'ftp' => [
			'class' => '\gftp\FtpComponent',
			'driverOptions' => [
				'class' => \gftp\FtpProtocol::valueOf('sftp')->driver,
				'user' => 'me@somewhere.otrb',
				'pass' => 'PassW0rd',
				'host' => 'ssh.somewhere.otrb',
				'port' => 22,
				'timeout' => 120
			]
		]
	],
	// [...]
];
```


Examples
-----

You can find examples on [Yii2-gftp extension](https://github.com/hguenot/yii2-gftp) site.
