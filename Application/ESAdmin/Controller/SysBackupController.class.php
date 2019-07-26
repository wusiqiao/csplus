<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;

class SysBackupController extends DataController {

    /**
     * 备份列表
     * @param $json    为NULL输出模板。为1时输出列表数据到前端，格式为Json
     * @examlpe 
     */
    public function index($json = NULL) {
        import('ORG.Net.FileSystem');
        $path = new FileSystem();
        $path->root = ITEM;
        $path->charset = C('CFG_CHARSET');
        if (!is_int((int) $json)) {
            $json = NULL;
        }
        if ($json == 1) {
            $url = ROOT . 'Conf/Backup';
            $info = $path->path($url, 1);
            $new_info = array();
            foreach ($info as $t) {
                $t['size'] = round($path->dirSize(CONF_PATH . 'Backup/' . $t['name']) / 1204, 2) . ' MB';
                $path->clearInfo();
                $t['addtime'] = date("Y-m-d H:i:s", $t['addtime']);
                $new_info[] = $t;
            }
            echo json_encode($new_info);
            unset($group, $info, $new_info, $path);
        } else {
            $mysql_version = mysql_get_server_info();
            $this->assign('mysql_version', $mysql_version);
            $this->display();
        }
        unset($Public);
    }

    /**
     * 备份数据库
     * @param $act   为1时输出数据表数
     * @examlpe 
     */
    public function bakdb($act = NULL) {
        if ($act == 1) {
            $arr_table['charset'] = I('charset');
            $arr_table['version'] = I('version');
            $arr_table['filesize'] = I('filesize');
            $arr_table['table'] = $this->getTable();  //获取数据表
            session('arr_table', serialize($arr_table));
            echo count($arr_table['table']);
        }
    }

    /**
     * 还原数据库
     * @param $act   为1时输出数据表数
     * @examlpe 
     */
    public function redb($act = NULL) {
        //实例化文件系统操作类
        import('ORG.Net.FileSystem');
        $path = new FileSystem();
        $path->root = ITEM;
        $path->charset = C('CFG_CHARSET');
        //main	
        if ($act == 1) {
            $file = I('file');
            $realfile = CONF_PATH . 'Backup/' . $file;
            $arr_table['path'] = $realfile;
            $arr_table['table'] = $path->nListPath($realfile);
            session('arr_table', serialize($arr_table));
            echo count($arr_table['table']);
        }
    }

    /**
     * 备份数据库
     * @param $act   bak为备份、re为还原
     * @param $total  传入表总数
     * @param $go  为1时，获取post
     * @examlpe 
     */
    public function show($act, $total = NULL, $go = -1) {
        $Public = A('Index', 'Public');
        $Public->check('Backup', array('c'));
        $sql = A('Sql', 'Public');      //实例化sql类
        import('ORG.Net.FileSystem');
        $path = new FileSystem();
        $path->root = ITEM;
        $path->charset = C('CFG_CHARSET');
        set_time_limit(1000);

        //main	
        if ($go >= 0) {
            if ($act == 'bak') {
                $arr_table = unserialize(session('arr_table'));
                //dump($arr_table);
                if ($go == count($arr_table['table'])) {
                    session('badate', NULL);
                    session('arr_table', NULL);
                    echo '所有表已完成备份！';
                    exit;
                }
                if (session('badate')) {
                    $badate = session('badate');
                } else {
                    $badate = date("Y-m-d_His");
                    session('badate', $badate);
                }
                $bak_dir = ROOT . '/Conf/Backup/' . $badate;
                if (!file_exists($bak_dir)) {
                    $path->putDir($bak_dir, 0777);
                }

                $strfile = '';
                $table = $arr_table['table'][$go];
                $tb = str_replace(C('DB_PREFIX'), '#@_', $table);
                $strfile .= "DROP TABLE IF EXISTS `" . $tb . "`;\r\n";
                $table_field = $sql->getField($table);  //获取表结构
                //替换数据表名
                $mysql = mysql_get_server_info();
                $get_field = preg_replace("/AUTO_INCREMENT=[0-9]+\s+/", "", $table_field);
                if ($arr_table['version'] == 4.1 && $mysql > 4.1) {
                    $get_field = preg_replace("/ENGINE=\b.{2,}\b DEFAULT CHARSET=\S+/", 'ENGINE=MyISAM DEFAULT CHARSET=' . $arr_table['charset'], $get_field);
                } elseif ($arr_table['version'] == 4.1 && $mysql < 4.1) {
                    $get_field = preg_replace("TYPE=\b.{2,}\b", 'ENGINE=MyISAM DEFAULT CHARSET=' . $arr_table['charset'], $get_field);
                } elseif ($arr_table['version'] == 4.0 && $mysql > 4.1) {
                    $get_field = preg_replace("/ENGINE=\b.{2,}\b DEFAULT CHARSET=\S+/", 'TYPE=MyISAM', $get_field);
                }
                $strfile .= str_replace('CREATE TABLE `' . C('DB_PREFIX'), 'CREATE TABLE `#@_', $get_field . ";\r\n");

                $result = M();
                $info = $result->table($table)->select();
                $p = 1;
                foreach ($info as $t) {
                    $strfile .= $sql->getData($table, $t);
                    if (strlen($strfile) >= $arr_table['filesize'] * 1024) {
                        $filename = $tb . '_' . $p . '.bak';
                        $fie_path = $bak_dir . '/' . $filename;
                        $path->putFile($fie_path, $strfile);
                        $p++;
                        $strfile = '';
                    }
                }
                if ($p == 1) {
                    $filename = $tb . '.bak';
                    $fie_path = $bak_dir . '/' . $filename;
                    $path->putFile($fie_path, $strfile);
                } else {
                    $filename = $tb . '_' . $p . '.bak';
                    $fie_path = $bak_dir . '/' . $filename;
                    $path->putFile($fie_path, $strfile);
                }
                echo '<p>表“' . $table . '”备份成功！</p>';
            } elseif ($act == 're') {
                $arr_table = unserialize(session('arr_table'));
                if ($go == count($arr_table['table'])) {
                    session('arr_table', NULL);
                    echo '所有表已完成还原！';
                    exit;
                }
                $table = str_replace('#@_', C('DB_PREFIX'), $arr_table['table'][$go]);
                $tb = str_replace('.bak', '', $table);
                $tablefile = $arr_table['path'] . '/' . $arr_table['table'][$go];
                $info = $path->getFile($tablefile);
                $arr_info = explode(";\r\n", $info);
                $result = M();
                foreach ($arr_info as $t) {
                    $t = preg_replace("/`#@_(.+)?`/iu", '`' . C('DB_PREFIX') . '$1`', $t);
                    $char = C('CFG_CHARSET');
                    if ($char == 'UTF-8') {
                        $char = 'utf8';
                    } else {
                        $char = 'gb2312';
                    }
                    $t = preg_replace("/ENGINE=\b.{2,}\b DEFAULT CHARSET=\S+/", 'ENGINE=MyISAM DEFAULT CHARSET=' . $char, $t);
                    $result->execute($t);
                }
                echo '<p>表“' . $tb . '”还原成功！</p>';
            }
        } else {
            $this->assign('act', $act);
            $this->assign('total', $total);
            $this->display();
        }
    }

    /**
     * 删除备份数据
     * @examlpe 
     */
    public function del() {
        $Public = A('Index', 'Public');
        $role = $Public->check('Backup', array('d'));
        if ($role < 0) {
            echo $role;
            exit;
        }
        import('ORG.Net.FileSystem');
        $path = new FileSystem();
        $path->root = ITEM;
        $path->charset = C('CFG_CHARSET');

        //main
        $file = I('file');
        $realfile = CONF_PATH . 'Backup/' . $file;
        if ($file && file_exists($realfile)) {
            $del = $path->delFile($realfile);
            if ($del) {
                echo 1;
            } else {
                echo 0;
            }
        } else {
            echo 2;
        }

        unset($Public, $path);
    }

    /**
     * 打包下载备份包
     * @param $file    文件路劲
     * @examlpe 
     */
    public function downzip($file) {
        import('ORG.Util.phpzip');
        $addzip = new phpzip();
        import('ORG.Net.FileSystem');
        $path = new FileSystem();
        $path->root = ITEM;
        $path->charset = C('CFG_CHARSET');
        load("@.download");

        //main
        $file = strval($file);
        $realpath = CONF_PATH . 'Backup/' . $file;
        $bakfile = RUNTIME_PATH . 'Temp/Zip/';
        if (!file_exists($bakfile)) {
            $path->putDir($bakfile);
        }
        $zipname = 'Backup_' . $file . '.zip';
        $zippath = $bakfile . $zipname;
        $addzip->zip($realpath, $zippath);
        if (file_exists($zippath)) {
            download($zippath);
            $path->delFile($zippath);
        }
        unset($addzip, $path);
    }

    private function getTable() {
        $db = M();
        $info = $db->query('show tables from ' . C('DB_NAME'));
        $infos = array();
        foreach ($info as $a) {
            $infos[] = $a['Tables_in_' . C('DB_NAME')];
        }
        return $infos;
        unset($info, $infos);
    }

    //获取数据表结构
    public function getField($table) {
        $db = M();
        $info = $db->query('show create table ' . $table);
        return $info[0]['Create Table'];
        unset($info);
    }

    ////格式化数据库数据
    public function getData($table, $row, $model = 'REPLACE') {
        $sql = $model . ' INTO `' . str_replace(C('DB_PREFIX'), '#@_', $table) . '` VALUES (';
        $values = '';
        foreach ($row as $val) {
            $values .= "'" . addcslashes($val, "'") . "',";
        }
        $sql .= substr($values, 0, -1) . ");\r\n";
        return $sql;
        unset($values);
    }

}
