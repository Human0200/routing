<?php
namespace Background\App;

class FieldMapper
{
    private const FIELD_MAPPING = [
        'title'           => 'TITLE',
        'message_id'      => 'XML_ID',
        'sender_email'    => 'UF_CRM_37_1750937812549',
        'sender_name'     => 'UF_CRM_37_1753094341',
        'date'            => 'UF_CRM_37_DATE_OF_ORDER',
        'attachment_name' => 'UF_CRM_37_1753097488',
        'xlsx_data'       => 'SOURCE_DESCRIPTION',
        'priority'        => 'UF_CRM_37_1749620161',
        'category'        => 'UF_CRM_37_CATEGORY',
        'description'     => 'UF_CRM_37_DESCRIPTION',
        'deadline_description' => 'UF_CRM_37_1749619917',
        'departament'     => 'UF_CRM_37_DIVISION',
        'deadline'     => 'UF_CRM_37_1749619843',
        'more_info'     => 'UF_CRM_37_1749636158',
    ];

    private const DEFAULT_VALUES = [
        'priority'       => 84,
        'category'       => 89,
        'assigned_by_id' => 83,
        'stage_id'       => 'DT1100_41:PREPARATION',
    ];

    public static function getFieldMap(): array
    {
        return self::FIELD_MAPPING;
    }

    public static function getFieldCode(string $fieldAlias): string
    {
        return self::FIELD_MAPPING[$fieldAlias] ?? $fieldAlias;
    }

    public static function getDefaultValue(string $field): mixed
    {
        return self::DEFAULT_VALUES[$field] ?? null;
    }
}
