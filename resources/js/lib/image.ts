async function draw(file: File, maxSize: number): Promise<HTMLCanvasElement> {
    if (!file.type.startsWith('image/')) throw new Error('Bukan gambar');

    const bitmap = await createImageBitmap(file);
    const scale = Math.min(1, maxSize / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    canvas.getContext('2d')!.drawImage(bitmap, 0, 0, canvas.width, canvas.height);

    return canvas;
}

/** Hasil berupa teks data:image/...;base64,... (dikirim sebagai field biasa, bukan upload file). */
export async function compressToDataUrl(
    file: File,
    maxSize = 800,
    type = 'image/webp',
    quality = 0.82,
): Promise<string> {
    const canvas = await draw(file, maxSize);

    return canvas.toDataURL(type, quality);
}

/** Logo struk: latar putih, hitam putih, kontras dinaikkan, hasil kecil. */
export async function compressLogo(file: File, maxSize = 384): Promise<string> {
    if (!file.type.startsWith('image/')) throw new Error('Bukan gambar');

    const bitmap = await createImageBitmap(file);
    const scale = Math.min(1, maxSize / Math.max(bitmap.width, bitmap.height));
    const w = Math.max(1, Math.round(bitmap.width * scale));
    const h = Math.max(1, Math.round(bitmap.height * scale));

    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d')!;

    ctx.fillStyle = '#ffffff'; // latar transparan menjadi putih, bukan hitam
    ctx.fillRect(0, 0, w, h);
    ctx.drawImage(bitmap, 0, 0, w, h);

    const img = ctx.getImageData(0, 0, w, h);
    const d = img.data;
    for (let i = 0; i < d.length; i += 4) {
        const gray = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2];
        const v = gray > 190 ? 255 : gray < 90 ? 0 : ((gray - 90) / 100) * 255; // tegaskan hitam dan putih
        d[i] = d[i + 1] = d[i + 2] = v;
    }
    ctx.putImageData(img, 0, 0);

    return canvas.toDataURL('image/png');
}