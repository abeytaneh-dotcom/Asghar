using System.Drawing.Drawing2D;

namespace KhanehRemapStudio;

public sealed class ModernButton : Button
{
    private bool _hover;
    private bool _pressed;
    public bool AccentMode { get; set; }
    public Color AccentColor { get; set; }=Color.FromArgb(47,226,185);

    public ModernButton()
    {
        FlatStyle=FlatStyle.Flat;
        FlatAppearance.BorderSize=0;
        BackColor=Color.Transparent;
        ForeColor=Color.White;
        Cursor=Cursors.Hand;
        SetStyle(ControlStyles.UserPaint|ControlStyles.AllPaintingInWmPaint|ControlStyles.OptimizedDoubleBuffer,true);
    }

    protected override void OnMouseEnter(EventArgs e){_hover=true;Invalidate();base.OnMouseEnter(e);}
    protected override void OnMouseLeave(EventArgs e){_hover=false;_pressed=false;Invalidate();base.OnMouseLeave(e);}
    protected override void OnMouseDown(MouseEventArgs mevent){_pressed=true;Invalidate();base.OnMouseDown(mevent);}
    protected override void OnMouseUp(MouseEventArgs mevent){_pressed=false;Invalidate();base.OnMouseUp(mevent);}

    protected override void OnPaint(PaintEventArgs e)
    {
        e.Graphics.SmoothingMode=SmoothingMode.AntiAlias;
        var r=new RectangleF(1,1,Width-2,Height-2);
        float radius=Math.Min(10,Height/3f);
        using var path=Round(r,radius);

        Color baseColor=AccentMode?Color.FromArgb(21,91,82):Color.FromArgb(22,35,50);
        if(_hover)baseColor=AccentMode?Color.FromArgb(25,117,103):Color.FromArgb(31,52,70);
        if(_pressed)baseColor=AccentMode?Color.FromArgb(18,72,68):Color.FromArgb(16,28,41);

        using var br=new LinearGradientBrush(r,
            Color.FromArgb(Math.Min(255,baseColor.R+7),Math.Min(255,baseColor.G+7),Math.Min(255,baseColor.B+7)),
            baseColor,LinearGradientMode.Vertical);
        e.Graphics.FillPath(br,path);

        using var pen=new Pen(AccentMode?Color.FromArgb(130,AccentColor):Color.FromArgb(65,89,111),1f);
        e.Graphics.DrawPath(pen,path);

        int imgW=Image?.Width??0;
        int gap=Image==null?0:7;
        var textSize=TextRenderer.MeasureText(Text,Font);
        int total=textSize.Width+imgW+gap;
        int x=(Width-total)/2;
        int cy=Height/2;

        if(Image!=null)
        {
            e.Graphics.DrawImage(Image,x,cy-Image.Height/2);
            x+=imgW+gap;
        }
        TextRenderer.DrawText(e.Graphics,Text,Font,new Point(x,cy-textSize.Height/2),ForeColor,
            TextFormatFlags.NoPadding|TextFormatFlags.NoPrefix);
    }

    private static GraphicsPath Round(RectangleF r,float radius)
    {
        float d=radius*2;
        var p=new GraphicsPath();
        p.AddArc(r.Left,r.Top,d,d,180,90);
        p.AddArc(r.Right-d,r.Top,d,d,270,90);
        p.AddArc(r.Right-d,r.Bottom-d,d,d,0,90);
        p.AddArc(r.Left,r.Bottom-d,d,d,90,90);
        p.CloseFigure();
        return p;
    }
}

public sealed class AccentPanel : Panel
{
    public Color EdgeColor { get; set; }=Color.FromArgb(47,226,185);
    public AccentPanel(){SetStyle(ControlStyles.UserPaint|ControlStyles.AllPaintingInWmPaint|ControlStyles.OptimizedDoubleBuffer,true);}
    protected override void OnPaint(PaintEventArgs e)
    {
        base.OnPaint(e);
        using var p=new Pen(Color.FromArgb(65,EdgeColor),1f);
        e.Graphics.DrawRectangle(p,0,0,Math.Max(0,Width-1),Math.Max(0,Height-1));
    }
}