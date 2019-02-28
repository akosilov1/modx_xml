<?php
define("MODULE_PATH",MODX_BASE_PATH."assets/modules/xls/");
require_once MODULE_PATH."lib/PHPExcel.php";
require_once MODX_BASE_PATH."assets/lib/MODxAPI/modResource.php";
$m_id = $_GET["id"];
$m_a = $_GET["a"];
$m_link = "?a=$m_a&id=$m_id";
if($_REQUEST["import-file"]){
	$xls = PHPExcel_IOFactory::load(MODULE_PATH."import/".$_REQUEST["import-file"]);
}else{
	$xls = PHPExcel_IOFactory::load(MODULE_PATH."import/example2.xls");
}

$sheet = $xls->getActiveSheet();
$h_row = $sheet->getHighestRow();
$h_col = PHPExcel_Cell::columnIndexFromString($sheet->getHighestColumn());

if($_REQUEST["action"] && $_SERVER["REQUEST_METHOD"] == "POST") {
    $ar_settings = array(
        'col' => $_REQUEST['col_'],
        'parent' => (int)$_REQUEST['parent'],
        'template' => (int)$_REQUEST['template'],
        'skipp' => (int)$_REQUEST['skipp'],
        'index' => $_REQUEST['index'],
        'file' => $_REQUEST['import-file'],
        'action_cols' => $_REQUEST['action_cols'],
        'set_cols' => $_REQUEST['set_cols'],
    );
    $ar_cols = $ar_settings["col"];
    $content_table = $modx->getFullTableName("site_content");

    switch ($_REQUEST["action"]) {
        case "clear":
            $ids = $modx->getDocumentChildren($_REQUEST["parent"],1,0,"id");
            $doc = new modResource($modx);
            foreach ($ids as $id){
                $doc->delete($id["id"]);
            }
            break;
        case "update":

            $tv_val_table = $modx->getFullTableName("site_tmplvar_contentvalues");
            $tv_table = $modx->getFullTableName("site_tmplvars");
            $res = $modx->db->select("id",$tv_table,"name='".$ar_settings["index"]."'");
            $index_id = $modx->db->getValue($res);
            $s_ok = 0;$s_err=0;
            for ($row = $ar_settings['skipp'] + 1; $row < $h_row; $row++) {
                $row_data = [];
                for ($col = 0; $col < $h_col; $col++) {
                    $row_data[$ar_settings['col'][$col]] = $sheet->getCellByColumnAndRow($col, $row)->getValue();
                }
                if($ar_settings["index"] == 'pagetitle'){
                    $d_id = $modx->getDocumentChildren($ar_settings["parent"], 1,0,"id","pagetitle='".addslashes($row_data["pagetitle"])."'");
                    $d_id = $d_id[0]["id"];
                }else{
                    $q = "SELECT
                        c.id
                        FROM
                        $content_table AS c
                        INNER JOIN $tv_val_table AS t ON c.id = t.contentid
                        WHERE
                        t.tmplvarid = $index_id AND
                        t.`value` = '".$modx->db->escape($row_data[$ar_settings["index"]])."'";

                    $res = $modx->db->query($q);
                    $d_id = $modx->db->getValue($res);
                }
                if($d_id){
                    $s_ok++;
                    $doc = new modResource($modx);
                    $doc->edit($d_id);
                    //print_r($row_data);
                    //die();
                    foreach ($row_data as $k => $val){
                        $doc->set($k, $val);
                    }
                    if($d_id = $doc->save(true,false))
                        echo $d_id." save<br>";

                }else{
                    echo $ar_settings["index"]."=".$row_data[$ar_settings["index"]]." err Ресурс не найден<br>";
                    $s_err++;
                }

            }
            echo "<hr><p>Обработанно $s_ok,<br>С ошибками $s_err</p>";
            save_settings($ar_settings);
            break;
        case "upload":
            // Сохраняем настройки
            save_settings($ar_settings);
            // Импорт
            // Обработка данных перед импортом
            function before_import($ar_rez){
                //pre_print($ar_rez);
                return $ar_rez;
            }
            $api = new modResource($modx);
            if ($_REQUEST['col_'] && in_array("pagetitle", $_REQUEST['col_'])) {

                $import = new import($sheet);
                $ar_rez = $import->run($ar_settings,$h_col, $h_row);
                echo "Добавлено: " . $ar_rez["ok"] . "<br>";
                echo "С ошибками: " . $ar_rez["bad"] . "<br>";

            } else {
                echo "Не выбрана калонка \"pagetitle\" (Название ресурса)";
            }

            break;
        case 'add_file':
            	if($_FILES && $_FILES['file']['error'] == 0){
            		copy($_FILES['file']['tmp_name'], MODULE_PATH."import/".$_FILES['file']['name']);
            		pre_print($_FILES);
            	}
            	header("Location: ".$m_link."&import-file=".$_FILES['file']['name']);
            break;
        case 'del_file':
            if($_REQUEST["import-file"]){
                unlink(MODULE_PATH . "import/" . $_REQUEST["import-file"]);
            }
            break;
        case "settings-save":

            break;
        default:

            break;
    }
    unset($_REQUEST);
}else{
    $settings = file_get_contents(MODULE_PATH . "profiles/config.txt");
    $ar_settings = json_decode($settings, true);
    if (is_array($ar_settings) && key_exists("col", $ar_settings)) {
        $ar_cols = $ar_settings["col"];
    }
}
//print_r($ar_cols);

$options = new options($ar_cols);
$options->get_table($h_col, $sheet);


//echo $h_row."<br>";
//echo $h_col."<br>";

//echo str_replace(["{{table}}","{{m_link}}"],[$table,$m_link],$template_main);
$s_index = "<option value='pagetitle'>pagetitle</option>";
foreach ($options->tvs_ as $tv_name){
    $selected = ($tv_name == $ar_settings["index"])?"selected":"";
    $s_index .= "<option value='{$tv_name}' $selected>{$tv_name}</option>";
}

// FILES
$files = "";
$f_dir = scandir(MODULE_PATH."import");
$i=0;$checked ="";
if($_REQUEST["import-file"]) $ar_settings['file'] = $_REQUEST["import-file"];
foreach ($f_dir as $key => $file) {
	if($file != "." AND $file != ".."){
		if($ar_settings['file']){
			$checked = ($file==$ar_settings['file'])?"checked":"";
		}elseif($i==0){
			$checked = "checked";
		}else{
			$checked = "";
		}
		$files .= "<label><input type='radio' name='import-file' value='".$file."' ".$checked."/>$file</label><br>";
		$i++;
	}
}
// END FILES
// TEMPLATE
$template = new template('main');
$template_data = array(
    'table'     => $options->table,
    'm_link'    => $m_link,
    "settings"  => $settings,
    "parent"    => $ar_settings["parent"],
    "template"  => $ar_settings["template"],
    "skipp"     => 1,
    "index"     => $s_index,
    "files"		=> $files,
);
$template->init($template_data);
$template->load();
// END TEMPLATE
function pre_print($data){
    echo "<pre>";
    print_r($data);
    echo "</pre>";
}
function save_settings($ar_settings){
    $f = fopen(MODULE_PATH . "profiles/config.txt", "w");
    fwrite($f, json_encode($ar_settings));
    fclose($f);
}
class import{
    var $api,$modx,$sheet,$call_back;
    function __construct($sheet)
    {   global $modx;
        $this->modx = $modx;
        $this->api = new modResource($modx);
        $this->sheet = $sheet;
    }

    function run($ar_settings, $h_col, $h_row){
        $ar_cols = $ar_settings['col'];
        $ar_rez = ["ok" => 0, "bad" => 0];
        $skipp = $_REQUEST['skipp'];
        for ($row = $skipp + 1; $row < $h_row; $row++) {
            $row_data = [];
            for ($col = 0; $col < $h_col; $col++) {
                $row_data[$ar_cols[$col]] = $this->sheet->getCellByColumnAndRow($col, $row)->getValue();
            }
            if (!key_exists('parent', $row_data))
                $row_data['parent'] = $ar_settings["parent"];
            if (!key_exists('template', $row_data))
                $row_data['template'] = $ar_settings["template"];
            if(function_exists('before_import')) $row_data = call_user_func('before_import', $row_data);
            $this->api->create($row_data);
            $id = $this->api->save(false, true);
            if ($id) $ar_rez["ok"]++;
            else $ar_rez["bad"]++;

        }
        return $ar_rez;
    }
}
class options{
    var $tvs_,$res_;
    var $ar_cols;
    var $table;
    function __construct($ar_cols)
    {
        global $modx;
        $this->ar_cols = $ar_cols;
        $tv_res = $modx->db->select("name",$modx->getFullTableName("site_tmplvars"));
        while ($n = $modx->db->getRow($tv_res,'assoc')){
            $this->tvs_[] = $n["name"];
        }
        $this->res_ = array(
            "pagetitle",
            "longtitle",
            "description",
            "alias",
            "link_attributes",
            "published",
            "parent",
            "isfolder",
            "introtext",
            "content",
            "template",
            "menuindex",
            "menutitle",
        );

    }
    function get_table($h_col,$sheet){
        $template = new template('table');
        $table = "<table id='set-table'>";
        $table .= "<tr>";
        for ($col = 0;$col < $h_col;$col++){
            $data = array(
                'options' => $this->get_options($col),
                'col' => $col,
            );
            //pre_print($data);
            $template->init($data);
            $table .= $template->get();
        }
        $table .= "</tr>";
        //
        for ($row = 1;$row < 10;$row++){
            $table .= "<tr>";
            for ($col = 0;$col < $h_col;$col++){
                $table .= "<td>".$sheet->getCellByColumnAndRow($col,$row)->getValue()."</td>";
            }
            $table .= "</tr>";
        }
        $table .= "</table>";
        $this->table = $table;
    }
    function get_options($col){
        $options = "<optgroup label='Content'>";
        foreach ($this->res_ as $d){
            $selected = ($this->ar_cols[$col] == $d)?"selected":"";
            $options.="<option value='$d' $selected >$d</option>";
        }
        $options.="</opgroup>";

        $options .= "<optgroup label='TVs'>";
        foreach( $this->tvs_ as $tvs){
            $selected = ($this->ar_cols[$col] == $tvs)?"selected":"";
            $options.="<option value='".$tvs."' $selected >".$tvs."</option>";
        }
        $options.="</opgroup>";
        return $options;
    }
}

class template{
    var $v_name, $v_val, $template;
    function __construct($name)
    {
        $this->template = file_get_contents(MODX_BASE_PATH."assets/modules/xls/templates/".$name.".html");
    }
    function init($data){
        $this->v_name = $this->v_val = array();
        foreach ($data as $d_name => $d_val){
            $this->v_name[] = "{{".$d_name."}}";
            $this->v_val[] = $d_val;
        }
    }
    function load(){
        echo str_replace($this->v_name,$this->v_val,$this->template);
    }
    function get(){
        return str_replace($this->v_name,$this->v_val,$this->template);
    }
}
/*
$resource = new modResource();
$resource->set();
*/