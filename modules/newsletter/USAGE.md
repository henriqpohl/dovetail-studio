# Newsletter — Usage

Enable **Newsletter** in **Dovetail Studio → Modules**, then open **Dovetail Studio → Newsletter**. Choose CF7, Mailchimp, or Both; select/save the CF7 shortcode; and configure Mailchimp only when required.

- CF7: sends the normal Contact Form 7 mail only.
- Mailchimp: subscribes the submitted email without the normal CF7 mail.
- Both: performs both actions. If Mailchimp fails, the CF7 mail is still sent and the visitor is told the subscription did not go through.

## Merge fields and tags

- **Merge fields:** one per line, `MERGETAG=cf7-field-name`, for example `FNAME=your-name` and `PHONE=your-phone`. The values the visitor typed in those CF7 fields are sent to Mailchimp with the subscription.
- **Tags:** comma-separated tags added to every new subscriber, for example `Website, Footer form`. A tag failure never undoes the subscription.

## Possible Errors and How to Fix Them

### Contact Form 7 warning is visible
Install and activate Contact Form 7. The module may remain enabled while the dependency is unavailable.
