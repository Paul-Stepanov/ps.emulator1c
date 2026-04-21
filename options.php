<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\HttpApplication;
use Bitrix\Main\Config\Option;

$module_id = 'ps.emulator1c';

$request = HttpApplication::getInstance()->getContext()->getRequest();

$aTabs = array(
    array(
        "DIV" => "edit1",
        "TAB" => "Настройки",
        "ICON" => "",
        "TITLE" => "Настройки модуля"
    )
);

$tabControl = new CAdminTabControl("tabControl", $aTabs);

if ($request->isPost() && check_bitrix_sessid()) {
    Option::set($module_id, "DEFAULT_SKIP_SOURCE_CHECK", $request->getPost("DEFAULT_SKIP_SOURCE_CHECK"));
    Option::set($module_id, "SECURE_1C_EXCHANGE", $request->getPost("SECURE_1C_EXCHANGE"));
}

$tabControl->Begin();
?>

<form method="post" action="<?echo $APPLICATION->GetCurPage()?>?mid=<?=htmlspecialcharsbx($request->get("mid"))?>&lang=<?=LANGUAGE_ID?>">
    <?=bitrix_sessid_post()?>
    <?php
    $tabControl->BeginNextTab();
    ?>
    <tr>
        <td width="40%">
            <label for="DEFAULT_SKIP_SOURCE_CHECK">Пропускать проверку источника:</label>
        </td>
        <td width="60%">
            <input type="checkbox" name="DEFAULT_SKIP_SOURCE_CHECK" value="Y" <?if(Option::get($module_id, "DEFAULT_SKIP_SOURCE_CHECK") == "Y") echo "checked";?>>
        </td>
    </tr>
    <tr>
        <td width="40%">
            <label for="SECURE_1C_EXCHANGE">Безопасный обмен с 1С:</label>
        </td>
        <td width="60%">
            <input type="checkbox" name="SECURE_1C_EXCHANGE" value="Y" <?if(Option::get($module_id, "SECURE_1C_EXCHANGE") == "Y") echo "checked";?>>
        </td>
    </tr>
    <?php
    $tabControl->Buttons();
    ?>
    <input type="submit" name="Update" value="Сохранить" class="adm-btn-save">
</form>

<?php
$tabControl->End(); 