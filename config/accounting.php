<?php

/*
| Accounting defaults. The base currency, fiscal-year start and lock date are editable in
| Admin → Accounting → Settings (stored in the settings table); these are the starting values.
*/

return [

    // Currency the books are kept in. Foreign-currency invoices and expenses are converted at their exchange rate.
    'base_currency' => env('ACCOUNTING_BASE_CURRENCY', 'PKR'),

    // First month of the financial year (7 = July, so the year runs July–June).
    'fiscal_year_start' => 7,

    /*
    | Starter chart of accounts, created on install. `subtype` marks the accounts the ledger posts to
    | automatically (receivable, payable, tax, retained, revenue, fx) and the ones money can be paid
    | into or out of (bank, cash). System accounts can be renamed but not deleted or retyped.
    */
    'chart' => [
        // code, name, type, subtype, system
        ['1000', 'Cash in hand', 'asset', 'cash', false],
        ['1010', 'Bank account', 'asset', 'bank', true],
        ['1100', 'Accounts receivable', 'asset', 'receivable', true],
        ['1200', 'Advance tax & prepayments', 'asset', null, false],
        ['1500', 'Computers & equipment', 'asset', null, false],
        ['2000', 'Accounts payable', 'liability', 'payable', true],
        ['2100', 'Sales tax payable', 'liability', 'tax', true],
        ['2200', 'Withholding tax payable', 'liability', null, false],
        ['2300', 'Salaries payable', 'liability', null, false],
        ['3000', "Owner's capital", 'equity', null, false],
        ['3100', "Owner's drawings", 'equity', null, false],
        ['3200', 'Retained earnings', 'equity', 'retained', true],
        ['4000', 'Software services revenue', 'income', 'revenue', true],
        ['4100', 'Other income', 'income', null, false],
        ['4200', 'Exchange gain / loss', 'income', 'fx', true],
        ['5000', 'Salaries & wages', 'expense', null, false],
        ['5100', 'Rent & utilities', 'expense', null, false],
        ['5200', 'Software & subscriptions', 'expense', null, false],
        ['5300', 'Hosting & cloud', 'expense', null, false],
        ['5400', 'Marketing & advertising', 'expense', null, false],
        ['5500', 'Contractors & freelancers', 'expense', null, false],
        ['5600', 'Bank charges', 'expense', null, false],
        ['5700', 'Office & equipment', 'expense', null, false],
        ['5800', 'Travel', 'expense', null, false],
        ['5900', 'Professional fees', 'expense', null, false],
        ['5950', 'Taxes & licences', 'expense', null, false],
        ['5990', 'Other expenses', 'expense', null, false],
    ],

];
