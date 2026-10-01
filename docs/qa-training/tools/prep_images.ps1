# Prepares PDF-friendly JPEG slices + video thumbnails from the full-size PNG screenshots (originals untouched).
$ErrorActionPreference = 'Stop'
$root = 'C:\xampp\htdocs\vaasal_villa_hospitality_management_system28\docs\qa-training'
$src = Join-Path $root 'screenshots'
$dst = Join-Path $root '_build\img'
$thumbs = Join-Path $root '_build\thumb'
New-Item -ItemType Directory -Force -Path $dst, $thumbs | Out-Null
Get-ChildItem $dst, $thumbs -Filter *.jpg | Remove-Item

Add-Type -ReferencedAssemblies System.Drawing -TypeDefinition @'
using System; using System.IO; using System.Collections.Generic;
using System.Drawing; using System.Drawing.Imaging; using System.Drawing.Drawing2D; using System.Runtime.InteropServices;
public static class Slicer {
    static ImageCodecInfo Jpeg() { foreach (var c in ImageCodecInfo.GetImageEncoders()) if (c.MimeType == "image/jpeg") return c; return null; }
    static void Save(Bitmap img, Rectangle srcRect, int dw, int dh, string path, long q) {
        using (var bmp = new Bitmap(dw, dh)) using (var g = Graphics.FromImage(bmp)) {
            g.InterpolationMode = InterpolationMode.HighQualityBicubic; g.PixelOffsetMode = PixelOffsetMode.HighQuality; g.SmoothingMode = SmoothingMode.HighQuality;
            g.DrawImage(img, new Rectangle(0, 0, dw, dh), srcRect, GraphicsUnit.Pixel);
            var ep = new EncoderParameters(1); ep.Param[0] = new EncoderParameter(System.Drawing.Imaging.Encoder.Quality, q);
            bmp.Save(path, Jpeg(), ep);
        }
    }
    // Cuts a tall screenshot into slices of about width*ratio pixels, choosing the calmest row near each cut.
    public static string Slice(string src, string dstDir, string name, int targetW, double ratio, long q) {
        using (var img = new Bitmap(src)) {
            int w = img.Width, h = img.Height;
            var data = img.LockBits(new Rectangle(0, 0, w, h), ImageLockMode.ReadOnly, PixelFormat.Format24bppRgb);
            int stride = data.Stride; byte[] buf = new byte[stride * h]; Marshal.Copy(data.Scan0, buf, 0, buf.Length); img.UnlockBits(data);
            Func<int, long> busy = y => { long s = 0; int o = y * stride; for (int x = 3; x < w * 3; x += 3) s += Math.Abs(buf[o + x] - buf[o + x - 3]) + Math.Abs(buf[o + x + 1] - buf[o + x - 2]) + Math.Abs(buf[o + x + 2] - buf[o + x - 1]); return s; };
            int sliceH = (int)(w * ratio);
            var cuts = new List<int> { 0 }; int pos = 0;
            while (h - pos > sliceH * 1.12) {
                int target = pos + sliceH, best = target; long bestS = long.MaxValue;
                for (int y = target - (int)(sliceH * 0.18); y <= target; y++) { long s = busy(y); if (s < bestS) { bestS = s; best = y; } }
                cuts.Add(best); pos = best;
            }
            cuts.Add(h);
            double scale = Math.Min(1.0, (double)targetW / w);
            var parts = new List<string>();
            for (int i = 0; i < cuts.Count - 1; i++) {
                int sh = cuts[i + 1] - cuts[i]; int dw = (int)Math.Round(w * scale), dh = (int)Math.Round(sh * scale);
                string file = name + "-" + (i + 1) + ".jpg";
                Save(img, new Rectangle(0, cuts[i], w, sh), dw, dh, Path.Combine(dstDir, file), q);
                parts.Add("{\"file\":\"" + file + "\",\"w\":" + dw + ",\"h\":" + dh + "}");
            }
            return "{\"name\":\"" + name + "\",\"w\":" + w + ",\"h\":" + h + ",\"slices\":[" + string.Join(",", parts) + "]}";
        }
    }
    // Top of the page at 16:10 (or the whole image if shorter) for storyboard thumbnails.
    public static void Thumb(string src, string dst, int tw) {
        using (var img = new Bitmap(src)) {
            int w = img.Width; int sh = Math.Min(img.Height, (int)(w * (w < 800 ? 1.9 : 0.625)));
            Save(img, new Rectangle(0, 0, w, sh), tw, (int)Math.Round(sh * (double)tw / w), dst, 78);
        }
    }
}
'@

$manifest = @()
foreach ($f in Get-ChildItem $src -Filter *.png | Sort-Object Name) {
    $name = $f.BaseName
    $w = [System.Drawing.Image]::FromFile($f.FullName); $width = $w.Width; $w.Dispose()
    if ($width -lt 800) { $json = [Slicer]::Slice($f.FullName, $dst, $name, $width, 2.75, 82) }
    else { $json = [Slicer]::Slice($f.FullName, $dst, $name, 1240, 1.22, 80) }
    [Slicer]::Thumb($f.FullName, (Join-Path $thumbs "$name.jpg"), $(if ($width -lt 800) { 260 } else { 560 }))
    $manifest += $json
}
'[' + ($manifest -join ",`n") + ']' | Set-Content -Encoding UTF8 (Join-Path $root '_build\images.json')
"Prepared $($manifest.Count) screenshots; slices: " + (Get-ChildItem $dst -Filter *.jpg).Count + '; size MB: ' + [Math]::Round(((Get-ChildItem $dst -Filter *.jpg | Measure-Object Length -Sum).Sum / 1MB), 1)
