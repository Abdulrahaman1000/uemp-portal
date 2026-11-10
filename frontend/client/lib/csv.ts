/** Downloads rows as a CSV file. Values that start with = + - @ are prefixed so spreadsheets don't run them as formulas. */
export function downloadCsv(filename: string, rows: (string | number)[][]): void {
  const cell = (value: string | number) => {
    const text = String(value);
    return `"${(/^[=+\-@]/.test(text) ? `'${text}` : text).replace(/"/g, '""')}"`;
  };
  const blob = new Blob([rows.map((row) => row.map(cell).join(",")).join("\n")], { type: "text/csv;charset=utf-8" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  link.click();
  URL.revokeObjectURL(url);
}
