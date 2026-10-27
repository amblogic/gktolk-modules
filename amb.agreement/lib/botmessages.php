<?php

namespace Amb\Agreement;

use Amb\Agreement\Db\AgreementObject;
use Bitrix\Main\Type\DateTime;

class Botmessages
{

    const MODULE_ID = 'amb.agreement';
    public const BX_APPROVE_TEXT = [
        0 => "Новый",
        1 => "Утвердил",
        2 => "Отклонил",
    ];
    public const BX_ANSWER_TEXT = [
        0 => "Новый",
        1 => "Утвердили",
        2 => "Отклонили",
    ];
    public const BX_APPROVE_COLOR = [
        0 => "Новый",
        1 => "#098702",
        2 => "#e92f2a",
    ];

    public static function getDbugPath()
    {
        return '/home/bitrix/agreement_botmessage_module.log';
    }

    public static function getApproveResultText($smartId, $userId, $result, $comment)
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        $text = "Пользователь ";
        try {

            $status_text = self::BX_APPROVE_TEXT[$result] ?? "--";
            $status_color = self::BX_APPROVE_COLOR[$result] ?? "#000";
            $userProps = AgreementManager::getUserProps($userId);
            $text = "[b][color={$status_color}]{$userProps['title']} {$status_text} документ.[/color][/b]";
            if (!empty($comment)) {
                $text .= " C комментарием:" . PHP_EOL . " {$comment}";
            }
            if ($dbug) {
                file_put_contents($dbug_path, date("[Y-m-d H:i:s]") . " getApproveResultText text: " . print_r($text, true) . "\n", FILE_APPEND);
            }
            return $text;
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("[Y-m-d H:i:s]") . " getApproveResultText Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
        return $text;
    }

    public static function getApproveStartText($smartFields)
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        $text = "Вам на согласование поступил договор ";
        try {

            if (!empty($smartFields)) {
                $text .= " {$smartFields["TITLE"]}";
            }
            if ($dbug) {
                file_put_contents($dbug_path, date("[Y-m-d H:i:s]") . "getApproveStartText text: " . print_r($text, true) . "\n", FILE_APPEND);
            }
            return $text;
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("[Y-m-d H:i:s]") . "getApproveStartText Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
        return $text;
    }

    public static function getBreakThroughText($smartFields)
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        $text = "Вам поступил договор";
        try {

            if (!empty($smartFields)) {
                $text .= " {$smartFields["TITLE"]}";
            }
            $text .= " для принудительного продолжения согласования после отклонения Юристом";
            if ($dbug) {
                file_put_contents($dbug_path, date("[Y-m-d H:i:s]") . "getBreakThroughText text: " . print_r($text, true) . "\n", FILE_APPEND);
            }
            return $text;
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("[Y-m-d H:i:s]") . "getBreakThroughText Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
        return $text;
    }

    public static function getNotifyDeadlineText(AgreementObject $Agreement)
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        $text = "[b][color=#ff0000]Скоро наступит дедлайн по договору: [/color][/b]";
        try {
            $text .= $Agreement->documentName;
            if ($dbug) {
                file_put_contents($dbug_path, date("[Y-m-d H:i:s]") . "getNotifyDeadlineText text: " . print_r($text, true) . "\n", FILE_APPEND);
            }
            $text .= PHP_EOL . "Вам необходимо принять решение до " . $Agreement->deadline->format("d.m.Y H:i");
            return $text;
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("[Y-m-d H:i:s]") . "getNotifyDeadlineText Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
        return $text;
    }

    public static function getInitApproveText($smartFields, $arrayUsers)
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        $text = "[b][color=#098702]Начато новое согласование по договору: [/color][/b]";
        try {
            if (!empty($smartFields)) {
                $smart_type = \Amb\Agreement\AgreementManager::getSmartTypeId();
                $text .= "[url=/crm/type/{$smart_type}/details/{$smartFields["ID"]}/]{$smartFields["TITLE"]}[/url]";
            }
            if ($dbug) {
                file_put_contents($dbug_path, date("[Y-m-d H:i:s]") . "getInitApproveText arrayUsers: " . print_r($arrayUsers, true) . "\n", FILE_APPEND);
            }
            if (is_array($arrayUsers) && !empty($arrayUsers)) {
                foreach ($arrayUsers as $level => $users) {
                    $text .= PHP_EOL . PHP_EOL . "[b]{$level}: [/b]";
                    if (is_array($users)) {

                        foreach ($users as $user) {
                            $userId = \CBPHelper::StripUserPrefix($user);
                            $User = \CUser::GetbyId($userId, false)->fetch();
                            if ($User) {
                                $text .= PHP_EOL . "    [ICON=/upload/icon-arrow-5083141.png title=Стрелка] ";
                                $text .= "{$User['LAST_NAME']} {$User['NAME']}";
                            }
                        }
                    } else {
                        $userId = \CBPHelper::StripUserPrefix($users);
                        $User = \CUser::GetbyId($userId, false)->fetch();
                        if ($User) {
                            $text .= PHP_EOL . "    [ICON=/upload/icon-arrow-5083141.png title=Стрелка] ";
                            $text .= "{$User['LAST_NAME']} {$User['NAME']}";
                        }
                    }
                }
            }
            $text .= PHP_EOL . "    " . PHP_EOL;
            return $text;
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("[Y-m-d H:i:s]") . "getInitApproveText Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
//        $array = [
//            "Согласование Службами" => $service_users,
//            "Согласование Юридическим отделом" => $urist,
//            "Согласование Генеральным Директором" => $gendir,
//        ];
//        $json = json_encode($array, JSON_UNESCAPED_UNICODE);
        return $text;
    }

    public static function getForgottenApproveText($agreements)
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        $agrCount = count($agreements);
        if ($agrCount > 1) {
            $text = "[b][color=#e92f2a]У вас просрочено {$agrCount} согласований: [/color][/b]";
        } else {
            $text = "[b][color=#e92f2a]У вас просрочено согласование: [/color][/b]";
        }
        try {
            $smart_type = \Amb\Agreement\AgreementManager::getSmartTypeId();
            $k = 1;
            $now = time();
            $text .= PHP_EOL;
            foreach ($agreements as $agreement) {
                $agrStart = $agreement->createdAt->getTimestamp();
                $dayDiff = floor(($now - $agrStart) / (60 * 60 * 24));
                $text .= PHP_EOL . "{$k}. [url=/crm/type/{$smart_type}/details/{$agreement->smartId}/]{$agreement->documentName}[/url] ({$dayDiff} дней)";
                $k++;
            }
            if ($dbug) {
                file_put_contents($dbug_path, date("[Y-m-d H:i:s]") . "getForgottenApproveText arrayUsers: " . print_r($arrayUsers, true) . "\n", FILE_APPEND);
            }

            $text .= PHP_EOL . "    " . PHP_EOL;
            return $text;
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("[Y-m-d H:i:s]") . "getForgottenApproveText Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }

        return $text;
    }

    public static function getForgottenStatisticText($UserAgreements)
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        $text = "[b][color=#e92f2a]Статистика просроченных согласований: [/color][/b]" . PHP_EOL;
        $now = time();
        try {
            foreach ($UserAgreements as $userId => $agreements) {
                $User = \CUser::GetbyId($userId, false)->fetch();
                $maxDays = 0;
                if ($User) {
                    $text .= PHP_EOL . "    [ICON=/upload/icon-arrow-5083141.png title=Стрелка] ";
                    $text .= "{$User['LAST_NAME']} {$User['NAME']}: ";
                    $agrCount = count($agreements);
                    $status_color = "#000";
                    $static_color = "#000";
                    if ($agrCount > 3) {
                        $status_color = "#e92f2a";
                    }
                    foreach ($agreements as $agreement) {
                        $agrStart = $agreement->createdAt->getTimestamp();
                        $dayDiff = floor(($now - $agrStart) / (60 * 60 * 24));
                        if ($maxDays < $dayDiff)
                            $maxDays = $dayDiff;
                    }
                    if ($maxDays > 3) {
                        $static_color = "#e92f2a";
                    }
                    $text .= "[b][color={$status_color}]{$agrCount} шт[/color][/b]. (Максимальная: {$maxDays} дней)";
                }
            }

            return $text;
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("[Y-m-d H:i:s]") . "getForgottenApproveText Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }

        return $text;
    }

    public static function getApproveAnswerText($smartId, $result, $Agreement = null)
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        $text = "Вы ";
        try {
            $status_text = self::BX_ANSWER_TEXT[$result] ?? "--";
            $status_color = self::BX_APPROVE_COLOR[$result] ?? "#000";
            $text .= "[b][color={$status_color}] {$status_text} [/color][/b] документ";
            if ($Agreement) {
                $smart_type = \Amb\Agreement\AgreementManager::getSmartTypeId();
                $text .= " [url=/crm/type/{$smart_type}/details/{$smartId}/]{$Agreement->documentName}[/url]";
            }
            if ($dbug) {
                file_put_contents($dbug_path, date("[Y-m-d H:i:s]") . " getApproveAnswerText text: " . print_r($text, true) . "\n", FILE_APPEND);
            }
            return $text;
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("[Y-m-d H:i:s]") . " getApproveAnswerText Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
        return $text;
    }
}
