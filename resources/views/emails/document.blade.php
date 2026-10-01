<!doctype html>
<html lang="en">
<body style="margin:0;background:#F4F6F3;font-family:Arial,Helvetica,sans-serif;color:#17211E;padding:16px">
<p style="max-width:760px;margin:0 auto 12px;font-size:14px">Please find your document from {{ property()->name }} below. Reply to this email with any questions.</p>
<div style="max-width:760px;margin:0 auto;background:#fff;border-radius:10px;padding:8px">
    @include($documentView, $data + ['embedded' => true])
</div>
</body>
</html>
