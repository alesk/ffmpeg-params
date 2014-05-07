# Testing effect of the transcoding params to overall picture quality

I want to test the effect of 2 parameters, namely the `fps` (Frames per second) and `bitrate` on overall picture quality.

The test is conducted by transcoding original movie with different values of fps and bitrate and 
collecting the following output parameters:

  - size of movie in kilobytes
  - # of I frames
  - Avg I frame Quality reduction
  - # of P frames
  - Avg P frame Quality reduction
  - # of B frames
  - Avg B frame Quality reduction



## Original video stream parameters 

## Mpeg1

`-bf <frames>` Use `<frames>` B-frames. To disable bframes, use `-bf 0`

## Video quality estimation

Use `-psnr` parameter to tell ffmpeg to calculate psnr values

## Other settings

`-vstats` Dump video coding statistics to vstats_HHMMSS.log
`-vstats_file`
