/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jkpk_article` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '管理员ID',
  `type` varchar(60) NOT NULL DEFAULT '' COMMENT '类型:sport,chinese_medical、science、food等',
  `title` varchar(120) NOT NULL DEFAULT '' COMMENT '标题',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态：1展示、0不展示',
  `is_sort` int(10) NOT NULL DEFAULT '0' COMMENT '排序，正序',
  `cover_image` varchar(120) NOT NULL DEFAULT '' COMMENT '封面图',
  `content` text COMMENT '内容',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COMMENT='文章表';
/*!40101 SET character_set_client = @saved_cs_client */;
