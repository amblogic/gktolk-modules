<?php
\Bitrix\Main\Loader::registerAutoLoadClasses('tolk.onlyoffice', [
    '\\Tolk\\OnlyOffice\\Jwt'            => 'lib/jwt.php',
    '\\Tolk\\OnlyOffice\\Options'        => 'lib/options.php',
    '\\Tolk\\OnlyOffice\\Formats'        => 'lib/formats.php',
    '\\Tolk\\OnlyOffice\\KeyStorage'     => 'lib/keystorage.php',
    '\\Tolk\\OnlyOffice\\FileResolver'   => 'lib/fileresolver.php',
    '\\Tolk\\OnlyOffice\\EventHandler'   => 'lib/eventhandler.php',
    '\\Tolk\\OnlyOffice\\Locker'         => 'lib/locker.php',
    '\\Tolk\\OnlyOffice\\CommandService' => 'lib/commandservice.php',
    '\\Tolk\\OnlyOffice\\Agent'          => 'lib/agent.php',
]);
