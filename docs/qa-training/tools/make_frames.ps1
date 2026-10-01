# Renders 1920×1080 still frames for the FFmpeg slideshow (docs/qa-training/video_frames), from frames.json.
$ErrorActionPreference = 'Stop'
$root = 'C:\xampp\htdocs\vaasal_villa_hospitality_management_system28\docs\qa-training'
$out = Join-Path $root 'video_frames'
Get-ChildItem $out -Filter *.png -ErrorAction SilentlyContinue | Remove-Item
Add-Type -ReferencedAssemblies System.Drawing -TypeDefinition @'
using System; using System.Drawing; using System.Drawing.Drawing2D; using System.Drawing.Imaging;
public static class Framer {
    public static void Frame(string src, string dst) {
        using (var img = new Bitmap(src)) using (var bmp = new Bitmap(1920, 1080)) using (var g = Graphics.FromImage(bmp)) {
            g.InterpolationMode = InterpolationMode.HighQualityBicubic; g.PixelOffsetMode = PixelOffsetMode.HighQuality; g.SmoothingMode = SmoothingMode.HighQuality;
            g.Clear(Color.FromArgb(14, 26, 24));
            int w = img.Width;
            if (w >= 800) {
                int h = Math.Min(img.Height, (int)Math.Round(w * 9.0 / 16.0));
                double s = Math.Min(1920.0 / w, 1080.0 / h);
                int dw = (int)Math.Round(w * s), dh = (int)Math.Round(h * s);
                g.DrawImage(img, new Rectangle((1920 - dw) / 2, (1080 - dh) / 2, dw, dh), new Rectangle(0, 0, w, h), GraphicsUnit.Pixel);
            } else {
                int h = Math.Min(img.Height, (int)Math.Round(w * 2.16));
                double s = 1000.0 / h;
                int dw = (int)Math.Round(w * s), dh = 1000;
                int x = (1920 - dw) / 2, y = 40;
                using (var pen = new Pen(Color.FromArgb(201, 160, 78), 6)) g.DrawRectangle(pen, x - 4, y - 4, dw + 7, dh + 7);
                g.DrawImage(img, new Rectangle(x, y, dw, dh), new Rectangle(0, 0, w, h), GraphicsUnit.Pixel);
            }
            bmp.Save(dst, ImageFormat.Png);
        }
    }
}
'@
$frames = Get-Content -Raw -Encoding UTF8 "$PSScriptRoot\frames.json" | ConvertFrom-Json
foreach ($f in $frames) { [Framer]::Frame((Join-Path $root "screenshots\$($f.shot).png"), (Join-Path $out $f.file)) }
"Frames: " + (Get-ChildItem $out -Filter *.png).Count + '; MB: ' + [Math]::Round(((Get-ChildItem $out -Filter *.png | Measure-Object Length -Sum).Sum / 1MB), 1)
