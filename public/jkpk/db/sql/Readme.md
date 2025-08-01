### 操作

```
    参数	说明
    --no-data 或 -d	只导出结构，不导出数据
    --skip-comments	不导出注释
    --skip-add-drop-table	不生成 DROP TABLE 语句
    --compact	生成更紧凑的输出
    --single-transaction	对InnoDB表使用事务保证一致性
    --routines	包含存储过程和函数
    --triggers	包含触发器
    --events	包含事件
```

#### Mysqldump
```
   user表
   - mysqldump -u root -p --no-data --skip-add-drop-table --compact jiankangpaika jkpk_user > jkpk_user.sql
   user_log表
   - mysqldump -u root -p --no-data --skip-add-drop-table --compact jiankangpaika jkpk_user_log > jkpk_user_log.sql
   jkpk_user_score_log表
   - mysqldump -u root -p --no-data --skip-add-drop-table --compact jiankangpaika jkpk_user_score_log > jkpk_user_score_log.sql
   jkpk_customer_message 表 提问表
   - mysqldump -u root -p --no-data --skip-add-drop-table --compact jiankangpaika jkpk_customer_message > jkpk_customer_message.sql
   jkpk_daily_task 表
   - mysqldump -u root -p --no-data --skip-add-drop-table --compact jiankangpaika jkpk_daily_task > jkpk_daily_task.sql
   jkpk_question 表
   - mysqldump -u root -p --no-data --skip-add-drop-table --compact jiankangpaika jkpk_question > jkpk_question.sql
   jkpk_check_calendar 表
   - mysqldump -u root -p --no-data --skip-add-drop-table --compact jiankangpaika jkpk_check_calendar > jkpk_check_calendar.sql
   jkpk_article 表
   - mysqldump -u root -p --no-data --skip-add-drop-table --compact jiankangpaika jkpk_article > jkpk_article.sql
```