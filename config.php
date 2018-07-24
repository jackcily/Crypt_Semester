<?php

require_once 'lang.php';

class Config {
    public static $password = array(
        'rules' => array(
            'length' => array(
                '/[\s\S]{6,36}/', '口令长度限制为6-36位'
            ),
            'number' => array(
                '/[0-9]+/', '口令需要至少一位数字'
            ),
            'lchars' => array(
                '/[A-Z]+/', '口令需要至少一个大写英文字母'
            ),
            'hchars' => array(
                '/[a-z]+/','口令至少一个小写英文字母'
            ),
            'orther' => array(
                '/[!@#$%^&*]+/','至少一个常用符号'
            )
        )
    );
    public static $uploadRoot = "/srv/acdemo/upload";
    public static $shareRoot = "/srv/acdemo/share";
    public static $debugLogFile = '/tmp/cucCloudPan.log';
    public static $symmetricEncKeyLen = 32;
    public static $asymmetricEncKeyLen = 32;
    public static $sessionTimeout = 60 * 10; // 10分钟没有活动就自动登出
    public static $pageSize = 3;
    public static $visiblePages = 5;
    public static $shaKeyRule = '/^[0-9a-z]{64,64}$/';
    public static $dftAllowedDldCount = 499;
    public static $dftDldExpHours = 1;
    public static $shareHashHmacAlgo = 'sha256';
    public static $shareKeyLen = 6;
    public static $nonceLen = 8;
}
