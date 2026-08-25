# Deploy Guide — Invoice Company Registration Details

**DEPLOYED** 9 April 2026

**Date:** 9 April 2026
**Branch:** `invoice`
**PR:** #205

---

## Summary

Added company registration details to the invoice PDF footer:
- Fynla Limited is registered in England & Wales, Company Number: 16903721
- Registered address: 124 City Road, London, England, EC1V 2NX

---

## Files to Upload

```
resources/views/invoices/pdf.blade.php --> ~/www/fynla.org/public_html/resources/views/invoices/
```

No frontend build needed. No migrations.

---

## SSH Commands (post-upload)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan view:clear
```

---

## Notes

- Existing invoices will show the new footer when regenerated or re-downloaded (PDF is rendered on-the-fly from the template)
- To regenerate an existing invoice PDF: `php artisan tinker` then `app(InvoiceService::class)->regeneratePdf(Invoice::find($id))`
