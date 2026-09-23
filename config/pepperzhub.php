<?php

return [
    /*
     * Keep the operator's saved appearance intact while temporarily forcing
     * the authenticated admin shell to render in light mode.
     */
    'admin_dark_mode_enabled' => (bool) env('ADMIN_DARK_MODE_ENABLED', false),
];
