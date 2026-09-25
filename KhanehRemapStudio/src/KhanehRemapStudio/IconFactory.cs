using System.Drawing.Drawing2D;

namespace KhanehRemapStudio;

public static class IconFactory
{
    public static Bitmap Create(string key,int size=24)
    {
        var b=new Bitmap(size,size);
        using var g=Graphics.FromImage(b);
        g.SmoothingMode=SmoothingMode.AntiAlias;
        g.Clear(Color.Transparent);

        var teal=Color.FromArgb(47,226,185);
        var blue=Color.FromArgb(89,164,255);
        var amber=Color.FromArgb(255,190,89);
        var white=Color.FromArgb(233,241,249);
        var dim=Color.FromArgb(145,166,188);
        key=(key??"").ToLowerInvariant();

        using var p=new Pen(teal,Math.Max(1.6f,size/13f)){StartCap=LineCap.Round,EndCap=LineCap.Round,LineJoin=LineJoin.Round};
        using var p2=new Pen(blue,Math.Max(1.5f,size/14f)){StartCap=LineCap.Round,EndCap=LineCap.Round,LineJoin=LineJoin.Round};
        using var pa=new Pen(amber,Math.Max(1.5f,size/14f)){StartCap=LineCap.Round,EndCap=LineCap.Round,LineJoin=LineJoin.Round};
        using var pw=new Pen(white,Math.Max(1.3f,size/15f)){StartCap=LineCap.Round,EndCap=LineCap.Round,LineJoin=LineJoin.Round};

        float s=size/24f;
        RectangleF R(float x,float y,float w,float h)=>new(x*s,y*s,w*s,h*s);
        PointF P(float x,float y)=>new(x*s,y*s);

        if(key.Contains("open")||key.Contains("folder")||key.Contains("باز"))
        {
            using var br=new SolidBrush(Color.FromArgb(38,blue));
            g.FillPath(br,FolderPath(s));
            g.DrawPath(p2,FolderPath(s));
        }
        else if(key.Contains("save")||key.Contains("ذخیره"))
        {
            g.DrawRectangle(p,R(4,3,16,18).X,R(4,3,16,18).Y,R(4,3,16,18).Width,R(4,3,16,18).Height);
            g.DrawRectangle(pw,R(7,4,8,6).X,R(7,4,8,6).Y,R(7,4,8,6).Width,R(7,4,8,6).Height);
            g.DrawRectangle(p2,R(7,13,10,6).X,R(7,13,10,6).Y,R(7,13,10,6).Width,R(7,13,10,6).Height);
        }
        else if(key.Contains("checksum")||key.Contains("چکسام")||key.Contains("shield"))
        {
            using var path=new GraphicsPath();
            path.AddLines(new[]{P(12,2),P(20,5),P(19,13),P(16,18),P(12,22),P(8,18),P(5,13),P(4,5),P(12,2)});
            g.DrawPath(p,path);
            g.DrawLines(pw,new[]{P(8,12),P(11,15),P(17,8)});
        }
        else if(key.Contains("help")||key.Contains("راهنما")||key=="?")
        {
            g.DrawEllipse(pa,R(3,3,18,18));
            using var f=new Font("Segoe UI",12*s,FontStyle.Bold,GraphicsUnit.Pixel);
            using var br=new SolidBrush(amber);
            var sf=new StringFormat{Alignment=StringAlignment.Center,LineAlignment=StringAlignment.Center};
            g.DrawString("?",f,br,R(3,2,18,19),sf);
        }
        else if(key.Contains("ecu")||key.Contains("chip"))
        {
            g.DrawRoundedRectangle(p,R(6,6,12,12),2*s);
            for(int i=0;i<4;i++)
            {
                float o=(7+i*3)*s;
                g.DrawLine(p2,2*s,o,6*s,o);g.DrawLine(p2,18*s,o,22*s,o);
                g.DrawLine(p2,o,2*s,o,6*s);g.DrawLine(p2,o,18*s,o,22*s);
            }
            g.DrawRectangle(pw,R(9,9,6,6).X,R(9,9,6,6).Y,R(9,9,6,6).Width,R(9,9,6,6).Height);
        }
        else if(key.Contains("car")||key.Contains("خودرو"))
        {
            using var path=new GraphicsPath();
            path.AddLines(new[]{P(4,14),P(6,9),P(9,6),P(16,6),P(19,10),P(21,14)});
            g.DrawPath(p2,path);g.DrawLine(p2,P(4,14),P(21,14));g.DrawLine(p2,P(5,14),P(5,18));g.DrawLine(p2,P(20,14),P(20,18));
            g.DrawEllipse(pw,R(6,16,3,3));g.DrawEllipse(pw,R(16,16,3,3));
        }
        else if(key.Contains("map")||key.Contains("table")||key.Contains("جدول"))
        {
            g.DrawRectangle(p,R(3,4,18,16).X,R(3,4,18,16).Y,R(3,4,18,16).Width,R(3,4,18,16).Height);
            for(int i=1;i<4;i++)g.DrawLine(pw,3*s,(4+i*4)*s,21*s,(4+i*4)*s);
            for(int i=1;i<3;i++)g.DrawLine(p2,(3+i*6)*s,4*s,(3+i*6)*s,20*s);
        }
        else if(key.Contains("graph")||key.Contains("compare")||key.Contains("مقایسه"))
        {
            g.DrawLine(pw,P(4,20),P(4,5));g.DrawLine(pw,P(4,20),P(21,20));
            g.DrawLines(p,new[]{P(5,17),P(9,13),P(12,15),P(16,8),P(21,5)});
            g.DrawLines(p2,new[]{P(5,15),P(9,16),P(12,11),P(16,12),P(21,8)});
        }
        else if(key.Contains("archive")||key.Contains("zip"))
        {
            g.DrawRectangle(p2,R(5,3,14,18).X,R(5,3,14,18).Y,R(5,3,14,18).Width,R(5,3,14,18).Height);
            g.DrawLine(pa,P(12,3),P(12,16));
            for(int i=0;i<5;i++)g.DrawRectangle(pw,R(10.8f,4+i*2.4f,2.4f,1.2f).X,R(10.8f,4+i*2.4f,2.4f,1.2f).Y,R(10.8f,4+i*2.4f,2.4f,1.2f).Width,R(10.8f,4+i*2.4f,2.4f,1.2f).Height);
        }
        else if(key.Contains("edit")||key.Contains("ویرایش")||key.Contains("wrench"))
        {
            g.DrawLine(p,P(5,19),P(17,7));g.DrawEllipse(p2,R(15,3,6,6));g.DrawEllipse(pw,R(3,17,4,4));
        }
        else if(key.Contains("undo"))
        {
            g.DrawArc(p2,R(5,5,15,14),210,260);g.DrawLines(p,new[]{P(6,5),P(3,10),P(9,10)});
        }
        else if(key.Contains("redo"))
        {
            g.DrawArc(p2,R(4,5,15,14),70,260);g.DrawLines(p,new[]{P(18,5),P(21,10),P(15,10)});
        }
        else if(key.Contains("file"))
        {
            using var path=new GraphicsPath();path.AddLines(new[]{P(6,3),P(15,3),P(20,8),P(20,21),P(6,21),P(6,3)});g.DrawPath(p2,path);
            g.DrawLines(pw,new[]{P(15,3),P(15,8),P(20,8)});
            g.DrawLine(p,P(9,12),P(17,12));g.DrawLine(p,P(9,16),P(15,16));
        }
        else
        {
            g.DrawEllipse(p,R(5,5,14,14));g.DrawEllipse(p2,R(9,9,6,6));
        }
        return b;
    }

    private static GraphicsPath FolderPath(float s)
    {
        PointF P(float x,float y)=>new(x*s,y*s);
        var path=new GraphicsPath();
        path.AddLines(new[]{P(3,7),P(9,7),P(11,9),P(21,9),P(19,20),P(3,20),P(3,7)});
        path.CloseFigure();return path;
    }

    private static void DrawRoundedRectangle(this Graphics g,Pen p,RectangleF r,float radius)
    {
        using var path=new GraphicsPath();
        float d=radius*2;
        path.AddArc(r.X,r.Y,d,d,180,90);path.AddArc(r.Right-d,r.Y,d,d,270,90);
        path.AddArc(r.Right-d,r.Bottom-d,d,d,0,90);path.AddArc(r.X,r.Bottom-d,d,d,90,90);path.CloseFigure();
        g.DrawPath(p,path);
    }
}