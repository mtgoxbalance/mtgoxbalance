CREATE TABLE `rawdata` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` char(64) NOT NULL,
  `data` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8;

