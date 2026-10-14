[![Latest Stable Version](https://poser.pugx.org/hguenot/yii2-gsftp/v/stable)](https://packagist.org/packages/hguenot/yii2-gsftp)
[![Total Downloads](https://poser.pugx.org/hguenot/yii2-gsftp/downloads)](https://packagist.org/packages/hguenot/yii2-gsftp)
[![License](https://poser.pugx.org/hguenot/yii2-gsftp/license)](https://packagist.org/packages/hguenot/yii2-gsftp)

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


Host key verification
---------------------

To protect against man-in-the-middle attacks, set `hostKeyFingerprint` to the SHA256 fingerprint of the server host key.
The connection is refused if the key presented by the server does not match.
You can get the fingerprint with:

```
ssh-keyscan -p 22 ssh.somewhere.otrb 2>/dev/null | ssh-keygen -lf -
```

```php
'driverOptions' => [
	'class' => \gftp\FtpProtocol::valueOf('sftp')->driver,
	// [...]
	'hostKeyFingerprint' => 'SHA256:nThbg6kXUpJWGl7E1IGOCspRomTxdCARLviKw6E5SY8',
]
```

Private key authentication
--------------------------

Set `privateKeyFile` (RSA, ECDSA or Ed25519 key); `pass` is used as passphrase if the key is encrypted.
`publicKeyFile` is no longer needed and is ignored.

Examples
-----

You can find examples on [Yii2-gftp extension](https://github.com/hguenot/yii2-gftp) site.
