<?php
 /**
 * 读取CSV文件
 * @param string $csv_file csv文件路径
 * @param int $lines       读取行数
 * @param int $offset      起始行数
 * @return array|bool
 */
function read_csv_lines($csv_file = '', $columns = 0, $lines = 0, $offset = 0)
{
    if (!$fp = fopen($csv_file, 'r')) {
        return false;
    }
    $i = $j = 0;
    while (false !== ($line = fgets($fp))) {
        if ($i++ < $offset) {
            continue;
        }
        break;
    }
    $result = array();
    if ($lines == 0){
        $lines = 65536;
    }
    //定位到第一行
    if ($offset == 0){
        fseek($fp, SEEK_SET);
    }
    
    if ($columns == 0){
        $columns = 255;
    }
    while (($j++ < $lines) && !feof($fp)) {
        $data = array();
        $row = fgetcsv($fp);
        if (!$row) {break;}        
        foreach ($row as $key=>$value) {
            if ($key > $columns){
                break;
            }
            $data[$key] = mb_convert_encoding($value, "UTF-8", "GBK");
        }
        $result[] = $data;
        unset($data);
        unset($row);
    }
    fclose($fp);
    return $result;
}