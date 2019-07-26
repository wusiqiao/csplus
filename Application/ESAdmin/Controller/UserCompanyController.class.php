<?php

namespace ESAdmin\Controller;

use Common\Lib\Controller\DataController;


class UserCompanyController extends DataController {

    protected $storage = [];
    public function showDominantFormAction(){
        $this->display();
    }
    public function addDominantAction(){
        if (IS_POST) {
            set_time_limit(0);
            if(!empty($_FILES)) {
                $uploader = getUploader("dominant/", array('xls', 'xlsx'));
                $info = $uploader->uploadOne($_FILES["file"]);
                if (!info) {
                    $message = buildMessage($uploader->getError(), 1);
                } else {
                    $filePath = ltrim($uploader->rootPath, ".") . $info['savepath'] . $info['savename'];
                    $message = $this->importDominantform($filePath);
                    unset($uploader);
                }
                var_dump($filePath);die;
                $this->responseJSON($message);
            } else {
                $this->responseJSON(buildMessage("文件不能为空！", 1));
            }
        }
    }
    public function exportDominantAction(){
        if (IS_POST) {
            set_time_limit(0);
            $filePath = ltrim(MODULE_UPLOAD_PATH, ".") . 'dominant/' . 'export_defult.xls';
            if (file_exists($filePath)) {
                $message = $this->importDominantform($filePath);
            } else {
                $this->responseJSON(buildMessage("导出数据的模板文件不存在！", 1));
            }
        }
    }
    public function importDominantform($excel_file){
        $filePath = realpath("./") . $excel_file;
        if(!file_exists($filePath)){
            return array('error'=>1,'message'=>'文件不存在!!');die;
        }
        //载入PHPExcel类型
        vendor('PHPExcel18.PHPExcel');
        $objectReader = \PHPExcel_IOFactory::createReaderForFile($filePath);
        $objPHPExcel  = $objectReader->load($filePath);
        $currentSheet = $objPHPExcel->getSheet(0);
        $this->handlerSheetData($currentSheet);
        if (empty($this->storage)) {
            return array('error'=>1,'message'=>'导入失败,没有所需数据!!');die;
        } else {
            //整理
            $this->handlerArrangement($this->storage);
            //去重
            $this->storage = $this->array_unique_fb($this->storage);
            //增加项 修改项
            D(CONTROLLER_NAME)->getCompanyListsByName();
        }
        var_dump($this->array_unique_fb($this->storage));die;
    }
    public function respondDominantform($excel_file){
        $filePath = realpath("./") . $excel_file;
        if(!file_exists($filePath)){
            return array('error'=>1,'message'=>'导出数据的模板文件不存在!!');die;
        }
        //获取公司数据
        $companys = [];
        //载入PHPExcel类型
        vendor('PHPExcel18.PHPExcel');
        $objectReader = \PHPExcel_IOFactory::createReaderForFile($filePath);
        $objPHPExcel  = $objectReader->load($filePath);
        $currentSheet = $objPHPExcel->getSheet(0);
        $this->handlerExportSheetData($currentSheet);
        //设置为第一个sheet
        $objPHPExcel->setActiveSheetIndex(0);
        //写入
        $row = 2;
        foreach($companys as $key => $val) {
            foreach($this->storage as $k => $v) {
                $objPHPExcel->getActiveSheet()->setCellValue($k.$row,$val[$v]);
            }
            $row++;
        }

    }
    /*
     * 匹配数据 - 导出
     */
    private function getMatchingRespondData(){
        $result = [
            ['value' => 'name','text' => '公司名称'],
            ['value' => 'linkman','text' => '联系人'],
            ['value' => 'contact','text' => '联系电话'],
        ];
        return $result;
    }
    /*
     * 匹配数据 - 导入
     */
    private function getMatchingData(){
        $result = [
            ['value' => 'name','text' => '公司名称'],
            ['value' => 'linkman','text' => '联系人'],
            ['value' => 'contact','text' => '联系电话'],
        ];
        return $result;
    }
    /*
     * 整理数据 - 空值处理
     */
    protected function handlerArrangement(&$data) {
        $matching = $this->getMatchingData();
        foreach($data as $key => $val){
            foreach($matching as $k => $v){
                if (!isset($val[$v['value']]) || empty($val[$v['value']])) {
                    $data[$key][$v['value']] = '';
                }
            }
        }
    }
    /*
     * 二维去重
     */
    protected function array_unique_fb($data){
        //去除指定不重复项
        $appiont = 'name';
        $company_names = [];
        foreach($data as $key => $val){
            if (!in_array($val[$appiont],$company_names)) {
                $company_names[] = $val[$appiont];
            } else {
                unset($data[$key]);
            }
        }
        $companys = [];
        foreach($data as $key =>$val){
            $temp_keys = array_keys($val);
            $temp[] = implode(',',$val);
        }
        $temps = array_unique($temp);
        foreach($temps as $k => $v) {
            $temp_value = explode(',',$v);
            foreach($temp_value as $ks =>$vs) {
                $temp_arr[$temp_keys[$ks]] = $vs;
            }
            $companys[] = $temp_arr;
        }
        return compact('companys','company_names');
    }
    /*
      * Creatre Sheet Get Date -- Transverse or Vertical
      * param  {object}  sheet  true: Transverse , false: Vertical
      * param  {inter}   show
      * Date Jan 26,2018
      */
    private function handlerSheetData($sheet){
        $heightRow    = $sheet->getHighestRow();
        $heightColumn = $sheet->getHighestColumn();
        //循环读取excel文件,读取一条,插入一条
        $data=array();
        $showTypeJ = 2;
        $showTypeK = ord('A');
        $showTypeJMax = $heightRow;
        $showTypeKMax = $this->getExcelColumnChar($heightColumn,false) ;
        $matching = $this->getMatchingData();
        for($j=$showTypeJ;$j<=$showTypeJMax;$j++){
            for($k=$showTypeK;$k<=$showTypeKMax;$k++){
                $kc       = $this->getExcelColumnChar($k,true);
                $cell     = $kc."1";
                $cell_value = "$kc$j";
                if (!empty(trim($sheet->getCell($cell_value)->getValue()))) {
                    foreach($matching as $key => $val) {
                        if (strpos(trim($sheet->getCell($cell)->getValue()),$val['text']) !== false){
                            $this->storage[$j][$val['value']] = trim($sheet->getCell($cell_value)->getValue());
                            break;
                        }
                    }
                }
            }
        }
    }
    /*
  * Creatre Sheet Get Date -- Transverse or Vertical
  * param  {object}  sheet  true: Transverse , false: Vertical
  * param  {inter}   show   1 A1 2 A 3 1A array显示方式不同
  * Date Jan 26,2018
  */
    private function handlerExportSheetData($sheet){
        $heightRow    = $sheet->getHighestRow();
        $heightColumn = $sheet->getHighestColumn();
        //循环读取excel文件,读取一条,插入一条
        $data=array();
        $showTypeK = ord('A');
        $showTypeKMax = $this->getExcelColumnChar($heightColumn,false) ;
        $matching = $this->getMatchingRespondData();
        for($k=$showTypeK;$k<=$showTypeKMax;$k++){
            $kc       = $this->getExcelColumnChar($k,true);
            $cell     = $kc."1";
            if (!empty(trim($sheet->getCell($cell)->getValue()))) {
                foreach($matching as $key => $val) {
                    if (strpos(trim($sheet->getCell($cell)->getValue()),$val['text']) !== false){
                        $this->storage[$kc] = $val['value'];
                        break;
                    }
                }
            }
        }
    }
    /**
     * Create Format ASCII
     * @param $index
     * @param bool $getAsc
     * @return float|int|string
     */
    private function getExcelColumnChar($index,$getAsc = true){
        if($getAsc){
            if (chr($index) > "Z") {
                return "A" . chr($index - ord("Z") - 1 + ord("A"));
            } else {
                return chr($index);
            }
        }else{
            if(strlen($index) == 2 && is_string($index)){
                $start_str = "A";
                $end_str   = "Z";
                $str1 = substr_replace($index,'',1,1);
                $str2 = substr_replace($index,'',0,1);
                $cycle = (ord($end_str) - ord($start_str) + 1);
                $cycle_sum = $cycle *( ord($str1) - ord($start_str) + 1 );
                return $cycle_sum + ord($start_str) + ord($str2) - ord($start_str);
            }elseif(!is_string($index)){
                return $this->getExcelColumnChar($index,true);
            }else{
                return ord($index);
            }
        }
    }
}
