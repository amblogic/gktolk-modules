<?php

namespace Amb\Agreement\Db;

use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Main\Config\Option;
use Amb\Agreement\AgreementObject;

class AgreementhistoryTable extends \Bitrix\Iblock\ElementTable
{

    /**
     * Returns class of Object for current entity.
     *
     * @return string|EntityObject
     */
    public static function getObjectClass()
    {
        return AgreementObject::class;
    }

    public static function getMap()
    {
        $map = parent::getMap();
        return $map;
    }

    public static function getList(array $parameters = array())
    {
        $parameters['filter']['IBLOCK_ID'] = \Amb\Agreement\AgreementManager::IBLOCK_ID;

        return parent::getList($parameters);
    }

}
