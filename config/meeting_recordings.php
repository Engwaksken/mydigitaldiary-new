<?php

return [
    /*
    | Length of each part a browser recording is split into, in seconds.
    | Each part is uploaded and transcribed in the background while the
    | meeting continues. Keep it at or under 25 minutes (1500 s): the
    | gpt-4o transcription models reject longer audio, and smaller parts
    | finish faster after the meeting ends.
    */
    'part_seconds' => (int) env('MEETING_RECORDING_PART_SECONDS', 1200),
];
