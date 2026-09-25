using System.Drawing.Drawing2D;

namespace KhanehRemapStudio;

public sealed class LineGraphPanel : Panel
{
    private double[] _v=Array.Empty<double>(); private string _title="";
    public LineGraphPanel(){DoubleBuffered=true;ResizeRedraw=true;}
    public void SetData(IEnumerable<double> v,string title){_v=v?.ToArray()??Array.Empty<double>();_title=title;Invalidate();}
    protected override void OnPaint(PaintEventArgs e)
    {
        base.OnPaint(e); e.Graphics.SmoothingMode=SmoothingMode.AntiAlias;
        using var fg=new SolidBrush(Color.FromArgb(234,241,248)); using var muted=new SolidBrush(Color.FromArgb(130,153,177));
        using var grid=new Pen(Color.FromArgb(40,57,75)); using var line=new Pen(Color.FromArgb(38,214,174),2.2f);
        e.Graphics.DrawString(_title,new Font("Segoe UI",11.5f,FontStyle.Bold),fg,15,12);
        var p=new Rectangle(66,55,Math.Max(20,Width-92),Math.Max(20,Height-100));
        for(int i=0;i<=5;i++){int y=p.Top+i*p.Height/5;e.Graphics.DrawLine(grid,p.Left,y,p.Right,y);}
        if(_v.Length<2){e.Graphics.DrawString("داده کافی وجود ندارد.",Font,muted,p.Left+20,p.Top+25);return;}
        double min=_v.Min(),max=_v.Max(); if(Math.Abs(max-min)<1e-12)max=min+1;
        var pts=new PointF[_v.Length];
        for(int i=0;i<_v.Length;i++)pts[i]=new PointF(p.Left+(float)i/Math.Max(1,_v.Length-1)*p.Width,p.Bottom-(float)((_v[i]-min)/(max-min))*p.Height);
        e.Graphics.DrawLines(line,pts);
        e.Graphics.DrawString(max.ToString("0.###"),new Font("Consolas",9f),muted,5,p.Top-5);
        e.Graphics.DrawString(min.ToString("0.###"),new Font("Consolas",9f),muted,5,p.Bottom-12);
    }
}

public sealed class SurfaceGraphPanel : Panel
{
    private double[,]? _m; private string _title="";
    public SurfaceGraphPanel(){DoubleBuffered=true;ResizeRedraw=true;}
    public void SetData(double[,]? m,string title){_m=m;_title=title;Invalidate();}
    protected override void OnPaint(PaintEventArgs e)
    {
        base.OnPaint(e);e.Graphics.SmoothingMode=SmoothingMode.AntiAlias;
        using var fg=new SolidBrush(Color.FromArgb(234,241,248));using var muted=new SolidBrush(Color.FromArgb(130,153,177));
        using var wire=new Pen(Color.FromArgb(38,214,174),1.5f);
        e.Graphics.DrawString(_title,new Font("Segoe UI",11.5f,FontStyle.Bold),fg,15,12);
        if(_m==null||_m.Length==0){e.Graphics.DrawString("داده کافی وجود ندارد.",Font,muted,40,80);return;}
        int rows=_m.GetLength(0),cols=_m.GetLength(1);double min=double.MaxValue,max=double.MinValue;
        foreach(var v in _m){min=Math.Min(min,v);max=Math.Max(max,v);} if(Math.Abs(max-min)<1e-12)max=min+1;
        float ox=Width*.14f,oy=Height*.80f,sx=Math.Max(7f,Width*.60f/Math.Max(1,cols-1)),sy=Math.Max(6f,Height*.28f/Math.Max(1,rows-1)),zs=Math.Max(40f,Height*.34f);
        PointF P(int r,int c)=>new(ox+c*sx+r*sx*.28f,oy-r*sy-(float)((_m[r,c]-min)/(max-min))*zs);
        for(int r=0;r<rows;r++)if(cols>1)e.Graphics.DrawLines(wire,Enumerable.Range(0,cols).Select(c=>P(r,c)).ToArray());
        for(int c=0;c<cols;c++)if(rows>1)e.Graphics.DrawLines(wire,Enumerable.Range(0,rows).Select(r=>P(r,c)).ToArray());
        e.Graphics.DrawString($"Min {min:0.###}   Max {max:0.###}",new Font("Consolas",9f),muted,15,Height-32);
    }
}

public sealed class DarkColorTable : ProfessionalColorTable
{
    public override Color ToolStripGradientBegin=>Color.FromArgb(8,13,21);
    public override Color ToolStripGradientMiddle=>Color.FromArgb(8,13,21);
    public override Color ToolStripGradientEnd=>Color.FromArgb(8,13,21);
    public override Color ToolStripBorder=>Color.FromArgb(31,47,64);
    public override Color ButtonSelectedHighlight=>Color.FromArgb(31,59,77);
    public override Color ButtonSelectedGradientBegin=>Color.FromArgb(31,59,77);
    public override Color ButtonSelectedGradientMiddle=>Color.FromArgb(31,59,77);
    public override Color ButtonSelectedGradientEnd=>Color.FromArgb(31,59,77);
    public override Color ButtonPressedGradientBegin=>Color.FromArgb(23,83,95);
    public override Color ButtonPressedGradientMiddle=>Color.FromArgb(23,83,95);
    public override Color ButtonPressedGradientEnd=>Color.FromArgb(23,83,95);
    public override Color SeparatorDark=>Color.FromArgb(48,65,82);
    public override Color SeparatorLight=>Color.FromArgb(48,65,82);
}