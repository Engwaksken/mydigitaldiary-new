<?php

/*
|--------------------------------------------------------------------------
| Engagement review route replacements (APPLIED)
|--------------------------------------------------------------------------
|
| This patch has been applied — do not require this file.
|
| The week/month routes now live in:
|   routes/web.php  — EngagementReviewController::week/month (auth + verified + subscribed)
|   routes/api.php  — Api\EngagementReviewController::week/month (auth:sanctum + mobile.idempotent)
|
| The old week/month definitions were removed so the route list has no
| duplicate URIs/names. today/checkin/share-card remain on EngagementController.
*/
