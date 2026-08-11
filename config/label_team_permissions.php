<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Label Team Permission Catalogue
    |--------------------------------------------------------------------------
    |
    | This module belongs ONLY to the Label Panel.
    |
    | Label Owner:
    |     Always has unrestricted access to its own permitted label boundary.
    |
    | Team User:
    |     Receives only explicitly granted permissions.
    |
    */

    'permissions' => [

        'dashboard.view' => [
            'label' => 'Dashboard',
            'group' => 'General',
            'standard' => true,
            'advanced' => true,
        ],

        'releases.view' => [
            'label' => 'View Releases',
            'group' => 'Releases',
            'standard' => true,
            'advanced' => true,
        ],

        'releases.create' => [
            'label' => 'Create Release',
            'group' => 'Releases',
            'standard' => true,
            'advanced' => true,
        ],

        'releases.edit' => [
            'label' => 'Edit Release',
            'group' => 'Releases',
            'standard' => true,
            'advanced' => true,
        ],

        'releases.delete_draft' => [
            'label' => 'Delete Draft Release',
            'group' => 'Releases',
            'standard' => true,
            'advanced' => true,
        ],

        /*
         * Critical action.
         * Standard users can never receive this permission.
         */
        'releases.submit' => [
            'label' => 'Submit Release',
            'group' => 'Releases',
            'standard' => false,
            'advanced' => true,
        ],

        'catalogue.view' => [
            'label' => 'View Catalogue',
            'group' => 'Catalogue',
            'standard' => true,
            'advanced' => true,
        ],

        'artists.view' => [
            'label' => 'View Artists',
            'group' => 'Artists',
            'standard' => true,
            'advanced' => true,
        ],

        'artists.manage' => [
            'label' => 'Manage Artists',
            'group' => 'Artists',
            'standard' => false,
            'advanced' => true,
        ],

        'reports.view' => [
            'label' => 'View Reports',
            'group' => 'Reports',
            'standard' => true,
            'advanced' => true,
        ],

        'royalties.view' => [
            'label' => 'View Royalties',
            'group' => 'Finance',
            'standard' => false,
            'advanced' => true,
        ],

        'statements.view' => [
            'label' => 'View Statements',
            'group' => 'Finance',
            'standard' => false,
            'advanced' => true,
        ],

        'invoices.view' => [
            'label' => 'View Invoices',
            'group' => 'Finance',
            'standard' => false,
            'advanced' => true,
        ],

        'wallet.view' => [
            'label' => 'View Wallet',
            'group' => 'Finance',
            'standard' => false,
            'advanced' => true,
        ],

        'withdrawals.view' => [
            'label' => 'View Withdrawals',
            'group' => 'Finance',
            'standard' => false,
            'advanced' => true,
        ],

        /*
         * Another critical action.
         */
        'withdrawals.create' => [
            'label' => 'Request Withdrawal',
            'group' => 'Finance',
            'standard' => false,
            'advanced' => true,
        ],

        'support.view' => [
            'label' => 'View Support',
            'group' => 'Support',
            'standard' => true,
            'advanced' => true,
        ],

        'support.create' => [
            'label' => 'Create Support Ticket',
            'group' => 'Support',
            'standard' => true,
            'advanced' => true,
        ],

        /*
         * Team users NEVER manage other team users.
         */
        'team.manage' => [
            'label' => 'Manage User Access',
            'group' => 'Security',
            'standard' => false,
            'advanced' => false,
            'owner_only' => true,
        ],

        /*
         * Revenue sharing remains owner-only.
         */
        'revenue_sharing.manage' => [
            'label' => 'Manage Revenue Sharing',
            'group' => 'Security',
            'standard' => false,
            'advanced' => false,
            'owner_only' => true,
        ],
    ],
];
