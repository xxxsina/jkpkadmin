/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jkpk_question` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `file` varchar(1000) DEFAULT NULL COMMENT '教学视频',
  `title` varchar(255) DEFAULT NULL COMMENT '标题',
  `switch` tinyint(5) DEFAULT '1' COMMENT '状态',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COMMENT='常见问题表';
/*!40101 SET character_set_client = @saved_cs_client */;
