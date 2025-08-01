/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jkpk_user_score_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '会员ID',
  `type` varchar(60) NOT NULL DEFAULT '' COMMENT '类型:add_score,check_in等',
  `score` int(10) NOT NULL DEFAULT '0' COMMENT '变更积分',
  `numb` int(8) NOT NULL DEFAULT '0' COMMENT '第几次加分',
  `before` int(10) NOT NULL DEFAULT '0' COMMENT '变更前积分',
  `after` int(10) NOT NULL DEFAULT '0' COMMENT '变更后积分',
  `memo` varchar(255) DEFAULT '' COMMENT '备注',
  `year` int(10) NOT NULL DEFAULT '0' COMMENT '年',
  `month` int(10) NOT NULL DEFAULT '0' COMMENT '月份',
  `day` int(10) NOT NULL DEFAULT '0' COMMENT '天',
  `unique_random` varchar(255) NOT NULL DEFAULT '' COMMENT '唯一数',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_unique_random` (`unique_random`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='会员积分变动表';
/*!40101 SET character_set_client = @saved_cs_client */;
