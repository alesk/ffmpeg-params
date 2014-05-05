import re

"""
frame=  123 fps= 34 q=-1.0 Lsize=     870kB time=00:00:15.12 bitrate= 471.2kbits/s dup=0 drop=239
video:628kB audio:236kB subtitle:0 data:0 global headers:0kB muxing overhead 0.691213%
[libx264 @ 0x7fd51b868400] frame I:15    Avg QP:30.99  size:  8709
[libx264 @ 0x7fd51b868400] frame P:75    Avg QP:37.02  size:  4612
[libx264 @ 0x7fd51b868400] frame B:3     Avg QP:35.50  size:  3735
[libx264 @ 0x7fd51b868400] consecutive B-frames: 93.5%  6.5%  0.0%  0.0%
[libx264 @ 0x7fd51b868400] mb I  I16..4: 29.4% 62.9%  7.7%
[libx264 @ 0x7fd51b868400] mb P  I16..4: 13.9% 26.8%  1.3%  P16..4: 26.9%  3.9%  1.2%  0.0%  0.0%    skip:26.0%
[libx264 @ 0x7fd51b868400] mb B  I16..4:  0.1%  1.5%  0.3%  B16..8: 38.0%  8.6%  1.5%  direct: 3.1%  skip:46.7%  L0:46.7% L1:45.5% BI: 7.8%
[libx264 @ 0x7fd51b868400] final ratefactor: 36.13
[libx264 @ 0x7fd51b868400] 8x8 transform intra:63.5% inter:85.2%
[libx264 @ 0x7fd51b868400] coded y,uvDC,uvAC intra: 27.5% 46.7% 10.8% inter: 12.4% 17.3% 0.8%
[libx264 @ 0x7fd51b868400] i16 v,h,dc,p: 20% 36%  9% 36%
[libx264 @ 0x7fd51b868400] i8 v,h,dc,ddl,ddr,vr,hd,vl,hu: 22% 20% 30%  5%  5%  5%  6%  4%  5%
[libx264 @ 0x7fd51b868400] i4 v,h,dc,ddl,ddr,vr,hd,vl,hu: 20% 26% 16%  5%  8%  7%  8%  4%  5%
[libx264 @ 0x7fd51b868400] i8c dc,h,v,p: 66% 18% 12%  4%
[libx264 @ 0x7fd51b868400] Weighted P-Frames: Y:8.0% UV:4.0%
[libx264 @ 0x7fd51b868400] ref P L0: 71.6% 11.0% 12.7%  4.7%  0.1%
[libx264 @ 0x7fd51b868400] ref B L0: 98.4%  1.6%
[libx264 @ 0x7fd51b868400] kb/s:335.64
"""

FFMPEG_STAT_PATTERNS=[
    re.compile('frame=\s*(?P<frame_count>\d+)\s+fps=\s*(?P<fps>[\d\.]+).*size=\s+(?P<size>\d+).*time=\s*(?P<time>[\d\.\:]+).*bitrate=\s*(?P<bitrate>[\d\.]+)'),
    re.compile('frame I:(?P<i_frame_count>\d+)\s+ Avg QP:(?P<i_frame_avg_qp>[\d\.]+)\s+size:\s+(?P<i_frame_size>\d+)'),
    re.compile('frame P:(?P<p_frame_count>\d+)\s+ Avg QP:(?P<p_frame_avg_qp>[\d\.]+)\s+size:\s+(?P<p_frame_size>\d+)'),
    re.compile('frame B:(?P<b_frame_count>\d+)\s+ Avg QP:(?P<b_frame_avg_qp>[\d\.]+)\s+size:\s+(?P<b_frame_size>\d+)'),
    re.compile('final rate factor: (?P<final_rate_factor>[\d\.]+)')
]
