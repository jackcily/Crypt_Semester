<?php
require_once 'utils.php';
require_once 'db.php';
require_once 'lang.php';
require_once 'config.php';





function doRegisterActive($postArr, &$pageLayout)
{
    // 数据校验

    $userName = $postArr['userName'];
    $verify = $postArr['verify'];

    try {
        //连接数据库
         if(checkRegisterActive($userName,$verify)==1)
         {
              $pageLayout['showRegFormOrNot'] ='hidden';
              echo  "激活成功";
         }else{
              $pageLayout['showRegFormOrNot'] ='hidden';
              echo "激活失败";
         }

        } catch(PDOException $e) {
        throw  $e ;
    }

}