<?php

if (!check_bitrix_sessid()) {
	return;
}
?>

<?php

// Сообщение при успешной установке модуля
echo CAdminMessage::ShowNote("Модуль успешно установлен");
