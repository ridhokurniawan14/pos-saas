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