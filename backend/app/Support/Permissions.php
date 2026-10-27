<?php

namespace App\Support;

/**
 * Права сотрудников и роли (см. docs/02-arhitektura/03-roli-i-prava.md).
 * Роли создаёт сидер RolesSeeder; здесь — единый справочник.
 */
final class Permissions
{
    public const CATALOG_EDIT = 'catalog.edit';
    public const PRICES_EDIT = 'prices.edit';
    public const CLIENTS_VIEW = 'clients.view';
    public const CLIENTS_VERIFY = 'clients.verify';
    public const CLIENTS_CREDIT = 'clients.credit';
    public const ORDERS_VIEW = 'orders.view';
    public const OFFERS_MANAGE = 'offers.manage';
    public const OFFERS_APPROVE_LOW_MARGIN = 'offers.approve_low_margin';
    public const COSTS_VIEW = 'costs.view';
    public const SUPPLIERS_MANAGE = 'suppliers.manage';
    public const PROCUREMENT_MANAGE = 'procurement.manage';
    public const PROCUREMENT_BEFORE_PAYMENT = 'procurement.before_payment';
    public const INVOICES_ISSUE = 'invoices.issue';
    public const BANK_IMPORT = 'bank.import';
    public const PAYMENTS_MATCH = 'payments.match';
    public const REFUNDS_REQUEST = 'refunds.request';
    public const REFUNDS_APPROVE = 'refunds.approve';
    public const DOCUMENTS_MANAGE = 'documents.manage';
    public const DOCUMENTS_PAPER_SIGN = 'documents.paper_sign';
    public const TEMPLATES_UPLOAD = 'templates.upload';
    public const TEMPLATES_APPROVE = 'templates.approve';
    public const SHIPMENTS_MANAGE = 'shipments.manage';
    public const ISSUES_MANAGE = 'issues.manage';
    public const AUDIT_VIEW = 'audit.view';
    public const STAFF_MANAGE = 'staff.manage';
    public const SETTINGS_MANAGE = 'settings.manage';

    public const ROLES = [
        'admin' => [
            'label' => 'Администратор',
            'permissions' => [self::CATALOG_EDIT, self::PRICES_EDIT, self::CLIENTS_VIEW, self::ORDERS_VIEW, self::TEMPLATES_UPLOAD, self::AUDIT_VIEW, self::STAFF_MANAGE, self::SETTINGS_MANAGE],
        ],
        'head' => [
            'label' => 'Руководитель',
            'permissions' => [self::CATALOG_EDIT, self::PRICES_EDIT, self::CLIENTS_VIEW, self::CLIENTS_VERIFY, self::CLIENTS_CREDIT, self::ORDERS_VIEW, self::OFFERS_MANAGE, self::OFFERS_APPROVE_LOW_MARGIN, self::COSTS_VIEW, self::SUPPLIERS_MANAGE, self::PROCUREMENT_MANAGE, self::PROCUREMENT_BEFORE_PAYMENT, self::INVOICES_ISSUE, self::REFUNDS_REQUEST, self::REFUNDS_APPROVE, self::DOCUMENTS_MANAGE, self::DOCUMENTS_PAPER_SIGN, self::TEMPLATES_APPROVE, self::SHIPMENTS_MANAGE, self::ISSUES_MANAGE, self::AUDIT_VIEW],
        ],
        'manager' => [
            'label' => 'Менеджер',
            'permissions' => [self::PRICES_EDIT, self::CLIENTS_VIEW, self::CLIENTS_VERIFY, self::ORDERS_VIEW, self::OFFERS_MANAGE, self::INVOICES_ISSUE, self::REFUNDS_REQUEST, self::DOCUMENTS_MANAGE, self::DOCUMENTS_PAPER_SIGN, self::ISSUES_MANAGE],
        ],
        'procurement' => [
            'label' => 'Закупщик',
            'permissions' => [self::CATALOG_EDIT, self::PRICES_EDIT, self::CLIENTS_VIEW, self::ORDERS_VIEW, self::COSTS_VIEW, self::SUPPLIERS_MANAGE, self::PROCUREMENT_MANAGE, self::ISSUES_MANAGE],
        ],
        'accountant' => [
            'label' => 'Бухгалтер',
            'permissions' => [self::CLIENTS_VIEW, self::CLIENTS_VERIFY, self::ORDERS_VIEW, self::INVOICES_ISSUE, self::BANK_IMPORT, self::PAYMENTS_MATCH, self::REFUNDS_REQUEST, self::REFUNDS_APPROVE, self::DOCUMENTS_MANAGE, self::DOCUMENTS_PAPER_SIGN, self::ISSUES_MANAGE],
        ],
        'logistics' => [
            'label' => 'Логист',
            'permissions' => [self::CLIENTS_VIEW, self::ORDERS_VIEW, self::SHIPMENTS_MANAGE, self::ISSUES_MANAGE],
        ],
        'lawyer' => [
            'label' => 'Юрист',
            'permissions' => [self::CLIENTS_VIEW, self::ORDERS_VIEW, self::DOCUMENTS_MANAGE, self::DOCUMENTS_PAPER_SIGN, self::TEMPLATES_UPLOAD, self::TEMPLATES_APPROVE],
        ],
    ];

    /** @return list<string> */
    public static function all(): array
    {
        $all = [];
        foreach (self::ROLES as $role) {
            $all = array_merge($all, $role['permissions']);
        }

        return array_values(array_unique($all));
    }
}
