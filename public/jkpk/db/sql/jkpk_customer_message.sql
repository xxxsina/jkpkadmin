/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jkpk_customer_message` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) NOT NULL DEFAULT '1' COMMENT '用户ID',
  `status` enum('new','answer') NOT NULL DEFAULT 'new' COMMENT '状态(new新问题，answer已回答)',
  `looked` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否查看(1是，0否)',
  `realname` varchar(30) DEFAULT '' COMMENT '姓名',
  `mobile` varchar(100) DEFAULT '' COMMENT '手机号',
  `problem` text COMMENT '遇到的问题',
  `answer` text COMMENT '回答',
  `image` varchar(255) NOT NULL DEFAULT '' COMMENT '图片地址',
  `video` varchar(255) NOT NULL DEFAULT '' COMMENT '视频地址',
  `is_overcome` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否解决，1是，0未处理，2否',
  `answer_image` varchar(255) NOT NULL DEFAULT '' COMMENT '回复上传的图片',
  `answer_video` varchar(255) NOT NULL DEFAULT '' COMMENT '回复上传的视频',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='客户问题表';
/*!40101 SET character_set_client = @saved_cs_client */;
