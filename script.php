<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/header.php');
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

function ReadCsvFile($filename) {
    $handle = fopen($filename, 'r');
    while (($row = fgetcsv($handle,$length=0,$delimiter=';',$enclosure='"')) !== false)
        $rows[] = $row;
    fclose($handle);
    $headers = array_shift($rows);
    $data = Array();
    foreach ($rows as $row)
        $data[] = array_combine($headers, $row);
    return $data;
}

function ReadDb($blockId) {
    $arSelect = Array('IBLOCK_ID', 'ID', 'CODE', 'PREVIEW_TEXT', 'DETAIL_TEXT');
    $arFilter = Array('IBLOCK_ID' => IntVal($blockId));
    $dbItems =  CIBlockElement::GetList(
        Array(),
        $arFilter, 
        false,
        false,
        $arSelect
    );

    $dbBlocksFields = Array();
    $dbBlocksProperties = Array();
    while($dbObj = $dbItems->GetNextElement($bTextHtmlAuto=true, $use_tilda=false)){
        $dbBlocksFields[] = $dbObj->GetFields();
        $dbBlocksProperties[] = $dbObj->GetProperties();
    }
    return [$dbBlocksFields, $dbBlocksProperties];
}

function AddIBlock($blockId, $date, $newData, $userId, $propId, $el){
    $PROP = array();
    $PROP[$propId] = $newData['prop1'];
    $PROP[$propId + 1] = $newData['prop2'];
    $dataToSave = Array( 
        'ACTIVE_FROM' => $date,
        'MODIFIED_BY' => $userId,
        'IBLOCK_SECTION_ID' => false,
        'IBLOCK_ID' => $blockId,
        'NAME' => $newData['name'], 
        'CODE' => Cutil::translit($newData['name'],'ru'),
        'ACTIVE' => 'Y',
        'PREVIEW_TEXT' => $newData['preview_text'], 
        'DETAIL_TEXT' => $newData['detail_text'], 
        'PROPERTY_VALUES'=> $PROP,
    );

    if($newElement = $el->Add($dataToSave))
        return '<br>ID Нового элемента: ' . $newElement; 
    else 
        return '<br>Error: ' . $el->LAST_ERROR;
}

function UpdateIBlockIfNeccessary($dbFields, $dbProperties, $newData, $userID, $propId, $el) {
    $fieldsToUpdate  = array();
    $PROP = array();
    if ($dbFields['PREVIEW_TEXT'] != $newData['preview_text'])
        $fieldsToUpdate['PREVIEW_TEXT'] = $newData['preview_text'];
    if ($dbFields['DETAIL_TEXT'] != $newData['detail_text'])
        $fieldsToUpdate['DETAIL_TEXT'] = $newData['detail_text'];

    if ($dbProperties['property1']['VALUE'] != $newData['prop1'])
        $PROP[$propId] = $newData['prop1'];
    if ($dbProperties['property2']['VALUE'] != $newData['prop2'])
        $PROP[$propId + 1] = $newData['prop2'];
    
    if (count($fieldsToUpdate) != 0 || count($PROP) != 0){
        $fieldsToUpdate['MODIFIED_BY'] = $userID;
        $fieldsToUpdate['IBLOCK_SECTION'] = false;
        if (count($PROP) != 0)
            $fieldsToUpdate['PROPERTY_VALUES'] = $PROP;

        if($el->Update($dbFields['ID'], $fieldsToUpdate))
            return '<br>ID Измененного элемента: ' . $dbFields['ID']; 
        else 
            return '<br>Error: ' . $el->LAST_ERROR;
    }
}

$file = 'files/test.csv';
$blockId = 12;
$propId = 63;

if(!CModule::IncludeModule('iblock')){
    echo 'Ошибка подключения модуля';
    die;
}

if (!is_file($file)) {
    echo 'Файл ' . $file . ' не существует';
    die;
}

$output = '';
$fileData = ReadCsvFile($file);
[$dbBlocksFields, $dbBlocksProperties] = ReadDb($blockId);

$el = new CIBlockElement;

foreach ($fileData as $newData) {
    $code = Cutil::translit($newData['name'],'ru');
    for ($i = 0; $i < count($dbBlocksFields); $i++){
        if ($dbBlocksFields[$i]['CODE'] == $code){
            $output = $output . UpdateIBlockIfNeccessary(
                $dbBlocksFields[$i], 
                $dbBlocksProperties[$i],
                $newData,
                $USER->GetID(),
                $propId,
                $el
            );
            continue 2;
        }
    }

    $output = $output . AddIBlock(
        $blockId,
        date('d.m.Y H:i:s'),
        $newData,
        $USER->GetID(),
        $propId,
        $el
    );
}

if ($output == '')
    $output = 'Изменений произведено не было';

echo $output;
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/footer.php');
?>
