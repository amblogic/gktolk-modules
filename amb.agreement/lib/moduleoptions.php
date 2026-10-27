<?php

namespace Amb\Agreement;

class ModuleOptions
{

    public $arCurOptionValues = array();
    private $moduleId = 'amb.agreement';
    private $arTabs = array();
    private $arGroups = array();
    private $arOptions = array();
    private $needAccessTab = false;

    public function __construct($moduleId, $arTabs, $arGroups, $arOptions, $needAccessTab = false)
    {
        $this->moduleId = $moduleId; // ID модуля
        $this->arTabs = $arTabs; // Вкладки
        $this->arGroups = $arGroups; // Группы (разделы опций)
        $this->arOptions = $arOptions; // Список опций
        $this->needAccessTab = $needAccessTab; // Нужна ли вкладка "Права доступа"

        if ($needAccessTab) {
            // Добавляем вкладку "Права доступа"
            $this->arTabs[] = array(
                'DIV' => 'edit_access_tab',
                'TAB' => 'Права доступа',
                'ICON' => '',
                'TITLE' => 'Настройка прав доступа'
            );
        }

        if ($_REQUEST['update'] == 'Y' && check_bitrix_sessid()) {
            // Сохранение параметров
            $this->SaveOptions();
        }

        // Список текущих параметров $this->arCurOptionValues
        $this->GetCurOptionValues();
    }

    /**
     * Сохранение параметров
     */
    private function SaveOptions()
    {
        foreach ($this->arOptions as $optionId => $arOptionItem) {
            if ($arOptionItem['TYPE'] === 'USER') {
                $val = serialize($_REQUEST[$optionId]);
                \COption::SetOptionString($this->moduleId, $optionId, $val);
                continue;
            }
            if ($arOptionItem['TYPE'] != 'CUSTOM') {
                $val = $_REQUEST[$optionId];

                if ($arOptionItem['TYPE'] == 'CHECKBOX' && $val != 'Y') {
                    $val = 'N';
                } else if (is_array($val)) {
                    $val = serialize($val);
                }

                \COption::SetOptionString($this->moduleId, $optionId, $val);
            }
        }
    }

    /**
     * Список текущих параметров
     */
    private function GetCurOptionValues()
    {
        foreach ($this->arOptions as $optionId => $arOptionItem) {
            if ($arOptionItem['TYPE'] != 'CUSTOM') {
                $this->arCurOptionValues[$optionId] = \COption::GetOptionString($this->moduleId, $optionId, $arOptionItem['DEFAULT']);

                if (in_array($arOptionItem['TYPE'], array('MSELECT', 'USER'))) {
                    $this->arCurOptionValues[$optionId] = unserialize($this->arCurOptionValues[$optionId]);
                }
            }
        }
    }

    /**
     * Форма параметров (в настройках модуля)
     */
    public function ShowHTML()
    {
        global $APPLICATION;

        $arParamsRender = array();

        // Создаем для каждой вкладки свою массив групп
        foreach ($this->arGroups as $groupId => $arGroupParams) {
            $tabId = $arGroupParams['TAB'];

            $arParamsRender[$tabId][$groupId] = array();
        }

        if (is_array($this->arOptions)) {
            // Формируем массив параметров для вывода
            foreach ($this->arOptions as $optionId => $arOptionItem) {
                $groupId = $arOptionItem['GROUP'];
                $tabId = $this->arGroups[$groupId]['TAB'];

                // Значение параметра
                $fieldValue = $this->arCurOptionValues[$optionId];

                if ($arOptionItem['SORT'] < 0 || !isset($arOptionItem['SORT'])) {
                    $arOptionItem['SORT'] = 0;
                }

                // Заголовок параметра
                $fieldLabel = (isset($arOptionItem['TITLE']) && $arOptionItem['TITLE'] != '') ? $arOptionItem['TITLE'] : '';

                $optionId = htmlspecialchars($optionId);

                // Тип параметра
                $field = '';
                switch ($arOptionItem['TYPE']) {
                    // Checkbox
                    case 'CHECKBOX':
                        $checked = $fieldValue == 'Y' ? ' checked' : '';
                        $refresh = $arOptionItem['REFRESH'] == 'Y' ? 'onclick="document.forms[\'' . $this->moduleId . '\'].submit();"' : '';

                        $field = '<input type="checkbox" name="' . $optionId . '" id="' . $optionId . '" value="Y"' . $checked . ' ' . $refresh . ' />';
                        break;

                    // Text, string
                    case 'TEXT':
                        $COLS = $arOptionItem['COLS'] ?? 25;
                        $ROWS = $arOptionItem['ROWS'] ?? 5;

                        $field = '<textarea cols="' . $COLS . '" rows="' . $ROWS . '" name="' . $optionId . '">' . htmlspecialchars($fieldValue) . '</textarea>';

                        if ($arOptionItem['REFRESH'] == 'Y') {
                            $field .= '<input type="submit" name="refresh" value="OK" />';
                        }
                        break;

                    // Select, список
                    case 'SELECT':
                        $field = SelectBoxFromArray($optionId, $arOptionItem['VALUES'], $fieldValue, '', '', ($arOptionItem['REFRESH'] == 'Y' ? true : false), ($arOptionItem['REFRESH'] == 'Y' ? $this->moduleId : ''));

                        if ($arOptionItem['REFRESH'] == 'Y') {
                            $field .= '<input type="submit" name="refresh" value="OK" />';
                        }
                        break;

                    // Multi Select, множественный список
                    case 'MSELECT':
                        $field = SelectBoxMFromArray($optionId . '[]', $arOptionItem['VALUES'], $fieldValue);

                        if ($arOptionItem['REFRESH'] == 'Y') {
                            $field .= '<input type="submit" name="refresh" value="OK" />';
                        }
                        break;

                    // Colorpicker, выбор цвета
                    case 'COLORPICKER':
                        $FIELD_SIZE = $arOptionItem['FIELD_SIZE'] ?? 25;

                        ob_start();
                        echo '<input id="__CP_PARAM_' . $optionId . '" name="' . $optionId . '" size="' . $FIELD_SIZE . '" value="' . htmlspecialchars($fieldValue) . '" type="text" style="float: left;" ' . ($arOptionItem['FIELD_READONLY'] == 'Y' ? 'readonly' : '') . ' />
								<script>
									function onSelect_' . $optionId . '(color, objColorPicker)
									{
										var oInput = BX("__CP_PARAM_' . $optionId . '");
										oInput.value = color;
									}
								</script>';

                        $APPLICATION->IncludeComponent('bitrix:main.colorpicker', '', Array(
                            'SHOW_BUTTON' => 'Y',
                            'ID' => $optionId,
                            'NAME' => 'Выбор цвета',
                            'ONSELECT' => 'onSelect_' . $optionId
                                ), false
                        );

                        $field = ob_get_clean();

                        if ($arOptionItem['REFRESH'] == 'Y') {
                            $field .= '<input type="submit" name="refresh" value="OK" />';
                        }
                        break;

                    // Colorpicker, выбор цвета
                    case 'USER':
                        $FIELD_SIZE = $arOptionItem['FIELD_SIZE'] ?? 25;
                        if (!is_array($fieldValue)) {
                            $fieldValue = [$fieldValue];
                        }
                        $inputName = ($arOptionItem['MULTIPLE'] === 'Y') ? $optionId . "[]" : $optionId;
                        ob_start();
                        $APPLICATION->IncludeComponent(
                                'bitrix:main.user.selector',
                                '',
                                [
                                    "ID" => "user_selector_module_" . $optionId,
                                    "API_VERSION" => 2,
                                    "LIST" => $fieldValue,
                                    "INPUT_NAME" => $inputName,
                                    "USE_SYMBOLIC_ID" => false,
                                    "BUTTON_SELECT_CAPTION" => "Выбрать",
                                    "SELECTOR_OPTIONS" =>
                                    [
                                        "departmentSelectDisable" => "Y",
                                        'context' => 'MAIL_CLIENT_CONFIG_QUEUE',
                                        'contextCode' => 'U',
                                        'enableAll' => 'N',
                                        'userSearchArea' => 'I'
                                    ]
                                ]
                        );

                        $field = ob_get_clean();

                        break;

                    case 'FILE':
                        $FIELD_SIZE = $arOptionItem['FIELD_SIZE'] ?? 25;
                        $BUTTON_TEXT = $arOptionItem['BUTTON_TEXT'] ?? '...';

                        \CAdminFileDialog::ShowScript(Array(
                            'event' => 'BX_FD_' . $optionId,
                            'arResultDest' => Array('FUNCTION_NAME' => 'BX_FD_ONRESULT_' . $optionId),
                            'arPath' => Array(),
                            'select' => 'F',
                            'operation' => 'O',
                            'showUploadTab' => true,
                            'showAddToMenuTab' => false,
                            'fileFilter' => '',
                            'allowAllFiles' => true,
                            'SaveConfig' => true
                        ));

                        $field = '<input id="__FD_PARAM_' . $optionId . '" name="' . $optionId . '" size="' . $FIELD_SIZE . '" value="' . htmlspecialchars($fieldValue) . '" type="text" style="float: left;" ' . ($arOptionItem['FIELD_READONLY'] == 'Y' ? 'readonly' : '') . ' />
									<input value="' . $BUTTON_TEXT . '" type="button" onclick="window.BX_FD_' . $optionId . '();" />
									<script>
										setTimeout(function(){
											if (BX("bx_fd_input_' . strtolower($optionId) . '"))
												BX("bx_fd_input_' . strtolower($optionId) . '").onclick = window.BX_FD_' . $optionId . ';
										}, 200);
										window.BX_FD_ONRESULT_' . $optionId . ' = function(filename, filepath)
										{
											var oInput = BX("__FD_PARAM_' . $optionId . '");
											if (typeof filename == "object")
												oInput.value = filename.src;
											else
												oInput.value = (filepath + "/" + filename).replace(/\/\//ig, \'/\');
										}
									</script>';

                        if ($arOptionItem['REFRESH'] == 'Y') {
                            $field .= '<input type="submit" name="refresh" value="OK" />';
                        }
                        break;

                    // Custom
                    case 'CUSTOM':
                        $field = $arOptionItem['VALUE'];
                        break;

                    default:
                        $SIZE = $arOptionItem['SIZE'] ?? 45;
                        $MAXLENGTH = $arOptionItem['MAXLENGTH'] ?? 255;

                        $field = '<input type="' . ($arOptionItem['TYPE'] == 'INT' ? 'number' : 'text') . '" size="' . $SIZE . '" maxlength="' . $MAXLENGTH . '" value="' . htmlspecialchars($fieldValue) . '" name="' . $optionId . '" />';

                        if ($arOptionItem['REFRESH'] == 'Y') {
                            $field .= '<input type="submit" name="refresh" value="OK" />';
                        }
                        break;
                }

                // Подсказка
                if (isset($arOptionItem['NOTES']) && $arOptionItem['NOTES'] != '') {
                    $field .= '<div class="notes">
									<table cellspacing="0" cellpadding="0" border="0" class="notes">
										<tbody>
											<tr class="top">
												<td class="left"><div class="empty"></div></td>
												<td><div class="empty"></div></td>
												<td class="right"><div class="empty"></div></td>
											</tr>
											<tr>
												<td class="left"><div class="empty"></div></td>
												<td class="content">
													' . $arOptionItem['NOTES'] . '
												</td>
												<td class="right"><div class="empty"></div></td>
											</tr>
											<tr class="bottom">
												<td class="left"><div class="empty"></div></td>
												<td><div class="empty"></div></td>
												<td class="right"><div class="empty"></div></td>
											</tr>
										</tbody>
									</table>
								</div>';
                }

                if ($fieldLabel != '') {
                    $arParamsRender[$tabId][$groupId]['OPTIONS'][] = '<tr><td valign="top" width="40%">' . $fieldLabel . '</td><td valign="top" nowrap>' . $field . '</td></tr>';
                } else {
                    $arParamsRender[$tabId][$groupId]['OPTIONS'][] = '<tr><td valign="top" colspan="2" align="center">' . $field . '</td></tr>';
                }

                $arParamsRender[$tabId][$groupId]['OPTIONS_SORT'][] = $arOptionItem['SORT'];
            }

            // Выводим форму с параметрами
            $tabControl = new \CAdminTabControl('tabControl', $this->arTabs);
            $tabControl->Begin();
            echo '<form name="' . $this->moduleId . '" method="POST" action="' . $APPLICATION->GetCurPage() . '?mid=' . $this->moduleId . '&lang=' . LANGUAGE_ID . '" enctype="multipart/form-data">' . bitrix_sessid_post();

            foreach ($arParamsRender as $tabId => $arGroups) {
                $tabControl->BeginNextTab();

                foreach ($arGroups as $groupId => $arGroupItem) {
                    if (sizeof($arGroupItem['OPTIONS_SORT']) > 0) {
                        echo '<tr class="heading"><td colspan="2">' . $this->arGroups[$groupId]['TITLE'] . '</td></tr>';

                        array_multisort($arGroupItem['OPTIONS_SORT'], $arGroupItem['OPTIONS']);
                        foreach ($arGroupItem['OPTIONS'] as $option) {
                            echo $option;
                        }
                    }
                }
            }

            if ($this->needAccessTab) {
                $tabControl->BeginNextTab();
                require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/admin/group_rights.php");
            }

            $tabControl->Buttons();

            echo '<input type="hidden" name="update" value="Y" />
					<input type="submit" name="save" value="Сохранить" />
					<input type="reset" name="reset" value="Отменить" />
					</form>';

            $tabControl->End();
        }
    }
}
