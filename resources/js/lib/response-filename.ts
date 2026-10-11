export function filenameFromResponse(
    response: Response,
    fallback: string,
): string {
    const header = response.headers.get('Content-Disposition') ?? '';
    const match = /filename="?([^";]+)"?/i.exec(header);

    return match?.[1]?.trim() || fallback;
}
