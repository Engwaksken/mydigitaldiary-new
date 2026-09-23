{{-- Legacy orphan — no longer @include'd anywhere. The responsive tab CSS
lives in profile/edit.blade.php; kept only for older external deployments. --}}
<style>
    .profile-tabs,
    .pm-profile-tabs {
        display:flex !important;
        flex-wrap:nowrap !important;
        overflow-x:auto !important;
        overflow-y:hidden !important;
        white-space:nowrap !important;
        -webkit-overflow-scrolling:touch;
    }

    .profile-tabs > *,
    .pm-profile-tabs > * {
        flex:0 0 auto !important;
        min-width:max-content !important;
        white-space:nowrap !important;
        word-break:normal !important;
        writing-mode:horizontal-tb !important;
    }
</style>
