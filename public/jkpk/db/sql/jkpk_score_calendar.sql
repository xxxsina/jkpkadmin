/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jkpk_score_calendar` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) NOT NULL DEFAULT '1' COMMENT '会员ID',
  `type` varchar(60) NOT NULL DEFAULT '' COMMENT '类型:add_score,check_in等',
  `numb` int(10) NOT NULL DEFAULT '0' COMMENT '获取积分次数',
  `is_complete` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否完成，1完成',
  `date_stamp` int(10) NOT NULL DEFAULT '0' COMMENT '日期',
  `year` int(10) NOT NULL DEFAULT '0' COMMENT '年',
  `month` int(10) NOT NULL DEFAULT '0' COMMENT '月份',
  `day` int(10) NOT NULL DEFAULT '0' COMMENT '天',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='积分日历表';
/*!40101 SET character_set_client = @saved_cs_client */;
