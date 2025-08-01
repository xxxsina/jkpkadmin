/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jkpk_queue_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `type` varchar(30) DEFAULT '' COMMENT '操作类型',
  `status` varchar(100) DEFAULT '0' COMMENT '是否已处理，1处理',
  `content` text COMMENT '数据内容',
  `createtime` bigint(16) DEFAULT NULL COMMENT '操作时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='队列失败记录表';
/*!40101 SET character_set_client = @saved_cs_client */;
