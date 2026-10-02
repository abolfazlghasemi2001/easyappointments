import math, subprocess, sys
from PIL import Image, ImageDraw, ImageFont, ImageFilter
W,H,FPS=1280,720,30; DUR=16.0; N=int(DUR*FPS)
import os
FD=os.environ.get("FONT_DIR","fonts")
VZ=os.path.join(FD,"Vazirmatn[wght].ttf")
PO=os.path.join(FD,"Poppins-Bold.ttf")
PR=os.path.join(FD,"Poppins-Regular.ttf")
from PIL import features
assert features.check("raqm"), "Pillow needs libraqm for Persian text"
OUT=os.environ.get("OUT","easyappointments_promo.mp4")
_fc={}
def fa(sz,w=700):
    k=('fa',sz,w)
    if k not in _fc:
        f=ImageFont.truetype(VZ,sz,layout_engine=ImageFont.Layout.RAQM)
        try: f.set_variation_by_axes([w])
        except Exception: pass
        _fc[k]=f
    return _fc[k]
def en(sz,bold=True):
    k=('en',sz,bold)
    if k not in _fc: _fc[k]=ImageFont.truetype(PO if bold else PR,sz)
    return _fc[k]
G1=(28,120,98); G2=(66,154,130); ACC=(255,196,61); WH=(255,255,255); DK=(20,50,45)
def clamp(x,a=0,b=1): return max(a,min(b,x))
def eo(t): t=clamp(t); return 1-(1-t)**3
def eob(t):
    t=clamp(t); c1=1.70158; c3=c1+1; return 1+c3*(t-1)**3+c1*(t-1)**2
def prog(t,a,b): return clamp((t-a)/(b-a))
bg=Image.new('RGB',(W,H))
d=ImageDraw.Draw(bg)
for y in range(H):
    r=y/H; d.line([(0,y),(W,y)],fill=tuple(int(G1[i]*(1-r)+(18,80,70)[i]*r) for i in range(3)))
def text(img,xy,s,font,fill=WH,alpha=1.0,anchor='mm',rtl=False):
    if alpha<=0: return
    lay=Image.new('RGBA',img.size,(0,0,0,0)); ld=ImageDraw.Draw(lay)
    kw={'direction':'rtl','language':'fa'} if rtl else {}
    ld.text(xy,s,font=font,fill=fill+(int(255*clamp(alpha)),),anchor=anchor,**kw)
    img.alpha_composite(lay)
def rrect(img,box,r,fill,alpha=1.0,shadow=False):
    if alpha<=0: return
    lay=Image.new('RGBA',img.size,(0,0,0,0)); ld=ImageDraw.Draw(lay)
    if shadow:
        sh=Image.new('RGBA',img.size,(0,0,0,0)); sd=ImageDraw.Draw(sh)
        x0,y0,x1,y1=box; sd.rounded_rectangle((x0,y0+10,x1,y1+10),r,fill=(0,0,0,int(70*alpha)))
        img.alpha_composite(sh.filter(ImageFilter.GaussianBlur(12)))
    ld.rounded_rectangle(box,r,fill=fill+(int(255*clamp(alpha)),))
    img.alpha_composite(lay)
def cal_icon(img,cx,cy,s,alpha=1):
    if s<=0.01 or alpha<=0: return
    w=int(170*s); h=int(160*s)
    x0,y0=cx-w//2,cy-h//2
    rrect(img,(x0,y0,x0+w,y0+h),int(26*s),WH,alpha,shadow=True)
    lay=Image.new('RGBA',img.size,(0,0,0,0)); ld=ImageDraw.Draw(lay); a=int(255*alpha)
    ld.rounded_rectangle((x0,y0,x0+w,y0+int(48*s)),int(26*s),fill=(230,80,70,a))
    ld.rectangle((x0,y0+int(26*s),x0+w,y0+int(48*s)),fill=(230,80,70,a))
    for dx in (-0.25,0.25):
        rx=cx+int(dx*w); ld.rounded_rectangle((rx-int(7*s),y0-int(16*s),rx+int(7*s),y0+int(18*s)),int(6*s),fill=(60,60,60,a))
    lw=max(2,int(14*s))
    ld.line([(cx-int(38*s),cy+int(22*s)),(cx-int(8*s),cy+int(50*s)),(cx+int(44*s),cy-int(8*s))],fill=G2+(a,),width=lw,joint='curve')
    img.alpha_composite(lay)
def particles(img,t):
    lay=Image.new('RGBA',img.size,(0,0,0,0)); ld=ImageDraw.Draw(lay)
    for i in range(26):
        x=(i*197+t*20*(1+i%3))%W; y=(i*131+math.sin(t*0.8+i)*30)%H
        r=3+i%5; ld.ellipse((x-r,y-r,x+r,y+r),fill=(255,255,255,28))
    img.alpha_composite(lay)
def scene_fade(t,a,b,f=0.4): return clamp(min((t-a)/f,(b-t)/f))

def frame(t):
    img=bg.copy().convert('RGBA'); particles(img,t)
    a=scene_fade(t,0,3.2) if t<3.2 else 0
    if t<0.4: a=1
    if a>0:
        s=eob(prog(t,0.1,0.9)); cal_icon(img,W//2,250,s,a)
        p=eo(prog(t,0.7,1.4)); text(img,(W//2,int(420+40*(1-p))),"Easy!Appointments",en(72),alpha=a*p)
        p2=eo(prog(t,1.2,1.9)); text(img,(W//2,int(510+30*(1-p2))),"سیستم نوبت‌دهی آنلاین، رایگان و متن‌باز",fa(36,500),fill=(220,245,238),alpha=a*p2,rtl=True)
    a=scene_fade(t,3.2,6.6)
    if a>0:
        p=eo(prog(t,3.3,3.9))
        text(img,(W//2,int(230-30*(1-p))),"هنوز نوبت‌ها رو با تلفن و دفترچه می‌گیری؟",fa(50,800),alpha=a*p,rtl=True)
        sh=math.sin(t*40)*6*(1 if 3.8<t<5.0 else 0)
        px=W//2-150+sh
        rrect(img,(px-50,330,px+50,500),18,(40,40,40),a*eo(prog(t,3.5,3.9)))
        rrect(img,(px-40,345,px+40,470),8,(120,200,230),a*eo(prog(t,3.5,3.9)))
        nx=W//2+150
        rrect(img,(nx-70,330,nx+70,500),10,(245,235,210),a*eo(prog(t,3.6,4.0)))
        lay=Image.new('RGBA',img.size,(0,0,0,0)); ld=ImageDraw.Draw(lay)
        for i in range(5): ld.line([(nx-50,360+i*28),(nx+50,360+i*28)],fill=(150,140,120,int(255*a)),width=3)
        px2=eo(prog(t,5.0,5.5))
        if px2>0:
            for cx in (W//2-150,W//2+150):
                L=90*px2
                ld.line([(cx-L,415-L),(cx+L,415+L)],fill=(230,70,60,int(255*a)),width=14)
                ld.line([(cx+L,415-L),(cx-L,415+L)],fill=(230,70,60,int(255*a)),width=14)
        img.alpha_composite(lay)
        p3=eo(prog(t,5.4,5.9)); text(img,(W//2,590),"وقتشه هوشمند بشی!",fa(44,800),fill=ACC,alpha=a*p3,rtl=True)
    a=scene_fade(t,6.6,10.6)
    if a>0:
        p=eo(prog(t,6.7,7.2))
        bx0,by0,bx1,by1=240,int(110+40*(1-p)),1040,int(560+40*(1-p))
        rrect(img,(bx0,by0,bx1,by1),24,WH,a*p,shadow=True)
        days=["شنبه","یکشنبه","دوشنبه","سه‌شنبه","چهارشنبه"]
        cw=(bx1-bx0-40)/5
        for i,dn in enumerate(days):
            cx=bx1-20-cw*(i+0.5)
            text(img,(int(cx),by0+40),dn,fa(24,700),fill=DK,alpha=a*p,rtl=True)
        times=["۹:۰۰","۱۱:۰۰","۱۴:۰۰","۱۶:۰۰"]
        booked=[(0,1),(2,0),(1,2),(3,3),(4,1),(0,3),(2,2),(3,0),(1,0),(4,3)]
        for r in range(4):
            for c in range(5):
                cx=bx1-20-cw*(c+0.5); cy=by0+115+r*95
                idx=booked.index((c,r)) if (c,r) in booked else None
                bp=eob(prog(t,7.4+idx*0.22,7.75+idx*0.22)) if idx is not None else 0
                col=tuple(int((235,242,240)[k]*(1-clamp(bp))+G2[k]*clamp(bp)) for k in range(3))
                rrect(img,(int(cx-cw/2+8),cy-36,int(cx+cw/2-8),cy+36),14,col,a*p)
                text(img,(int(cx),cy),times[r] if bp<0.5 else "رزرو شد",fa(22,700),fill=(WH if bp>=0.5 else (90,110,105)),alpha=a*p,rtl=True)
        p2=eo(prog(t,7.6,8.1)); text(img,(W//2,640),"مشتری‌ها خودشون ۲۴ ساعته آنلاین نوبت می‌گیرن",fa(38,800),alpha=a*p2,rtl=True)
    a=scene_fade(t,10.6,13.6)
    if a>0:
        p=eo(prog(t,10.7,11.1)); text(img,(W//2,120),"هر چیزی که لازم داری",fa(50,900),fill=ACC,alpha=a*p,rtl=True)
        feats=["همگام‌سازی با Google Calendar","یادآوری ایمیلی خودکار","مدیریت چند ارائه‌دهنده و خدمت","داده‌ها روی سرور خودت"]
        for i,f in enumerate(feats):
            q=eo(prog(t,11.0+i*0.3,11.5+i*0.3))
            col=i%2; row=i//2
            x0=150+col*510+int((1-q)*(-120 if col==0 else 120)); y0=220+row*190
            rrect(img,(x0,y0,x0+470,y0+150),22,WH,a*q,shadow=True)
            lay=Image.new('RGBA',img.size,(0,0,0,0)); ld=ImageDraw.Draw(lay)
            ld.ellipse((x0+380,y0+40,x0+450,y0+110),fill=G2+(int(255*a*q),)); img.alpha_composite(lay)
            text(img,(x0+415,y0+75),str(i+1),en(34),alpha=a*q)
            text(img,(x0+360,y0+75),f,fa(25,700),fill=DK,alpha=a*q,anchor='rm',rtl=True)
    a=clamp((t-13.6)/0.4)
    if a>0:
        s=eob(prog(t,13.7,14.3)); cal_icon(img,W//2,200,s*0.8,a)
        p=eo(prog(t,14.0,14.6)); text(img,(W//2,int(350+30*(1-p))),"Easy!Appointments",en(68),alpha=a*p)
        p2=eo(prog(t,14.3,14.9)); text(img,(W//2,440),"همین امروز راه‌اندازی کن. رایگان، برای همیشه.",fa(40,800),fill=ACC,alpha=a*p2,rtl=True)
        p3=eo(prog(t,14.7,15.2))
        pulse=1+0.04*math.sin(t*6)
        bw=int(760*pulse); bh=int(70*pulse)
        rrect(img,(W//2-bw//2,540-bh//2,W//2+bw//2,540+bh//2),bh//2,WH,a*p3,shadow=True)
        text(img,(W//2,540),"github.com/abolfazlghasemi2001/easyappointments",en(24),fill=G1,alpha=a*p3)
    return img.convert('RGB')

if len(sys.argv)>1:
    for ts in sys.argv[1:]: frame(float(ts)).save(f"still_{ts}.png")
    sys.exit()
ff=subprocess.Popen(['ffmpeg','-y','-f','rawvideo','-pix_fmt','rgb24','-s',f'{W}x{H}','-r',str(FPS),'-i','-','-c:v','libx264','-pix_fmt','yuv420p','-crf','20','-preset','fast',OUT],stdin=subprocess.PIPE,stderr=subprocess.DEVNULL)
for i in range(N): ff.stdin.write(frame(i/FPS).tobytes())
ff.stdin.close(); ff.wait(); print("done")
