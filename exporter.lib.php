<?php 

function claro_export_user_list($courseCode)
{    
    $tbl_mdb_names = claro_sql_get_main_tbl();
    $tbl_rel_course_user = $tbl_mdb_names['rel_course_user'  ];
    $tbl_users           = $tbl_mdb_names['user'             ];
    
    $sqlGetUsers = "SELECT `user`.`user_id`  AS `user_id`,
                       `user`.`nom`          AS `nom`,
                       `user`.`prenom`       AS `prenom`,
                       `user`.`email`        AS `email`,
                       `user`.`username`     AS `username`,
                       `user`.`isPlatformAdmin`,
                       `user`.`isCourseCreator`,
                       `course_user`.`profile_id`,
                       `course_user`.`isCourseManager`,
                       `course_user`.`tutor`  AS `tutor`,
                       `course_user`.`role`   AS `role`
               FROM `" . $tbl_users . "`           AS user,
                    `" . $tbl_rel_course_user . "` AS course_user
               WHERE `user`.`user_id`=`course_user`.`user_id`
               AND   `course_user`.`code_cours`='" . claro_sql_escape($courseCode) . "'
               ORDER BY `user`.`nom`,  `user`.`prenom` ;";
    
    return claro_sql_query_fetch_all_rows($sqlGetUsers);
}

function claro_export_forum_list($course, $categoryId)
{
    $tbl = get_module_course_tbl(array('bb_forums'), $course);
    
    $sql = "SELECT * FROM `{$tbl['bb_forums']}`
        WHERE `bb_forums`.`cat_id` = {$categoryId}  
        ORDER BY forum_order";
    
    return claro_sql_query_fetch_all_rows($sql);
}

function claro_export_category_list($course)
{
    $tbl = get_module_course_tbl(array('bb_categories'), $course);
    
    $sql = "SELECT * FROM `{$tbl['bb_categories']}`
        ORDER BY cat_order";
        
    return claro_sql_query_fetch_all_rows($sql);
}

function claro_export_topic_list($course, $forumId)
{
    $tbl = get_module_course_tbl(array('bb_topics'), $course);
    
    $sql = "SELECT * FROM `{$tbl['bb_topics']}`
        WHERE `bb_topics`.`forum_id` = {$forumId}";
        
    return claro_sql_query_fetch_all_rows($sql);
}

function claro_export_forum_post($course, $topicId)
{
    $tbl = get_module_course_tbl(array('bb_posts', 'bb_posts_text'), $course);
    $tblPosts     = $tbl['bb_posts'];
    $tblPostsText = $tbl['bb_posts_text'];
    
    $sql = "SELECT `post`.`poster_id`,
        `post`.`nom` as `last_name`,
        `post`.`prenom` as `first_name`,
        `post`.`post_time` as `post_time`,
        `post_text`.`post_text`
        FROM `" . $tblPosts . "` AS post,
        `" . $tblPostsText . "`  AS post_text
        WHERE `post_text`.`post_id` = `post`.`post_id`
        AND `post`.`topic_id` = {$topicId}";
        
    return claro_sql_query_fetch_all_rows($sql);
}

function claro_export_groups($course)
{
    $tbl = get_module_course_tbl(array('group_team'), $course);
    $tblGroups = $tbl['group_team'];
    
    $sql = "SELECT * FROM `" . $tblGroups . "`  ";
    
    return claro_sql_query_fetch_all_rows($sql);
}
