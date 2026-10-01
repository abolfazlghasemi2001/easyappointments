#!/usr/bin/env python3
"""Generate the project's small, currentColor barber-line icon library and sprite."""
from pathlib import Path
from html import escape

ROOT = Path(__file__).resolve().parents[1] / "assets" / "img" / "icons"

ICONS = {
    "barber-shop": {
        "scissors": ("Scissors", '<circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="m8.1 8.1 12.9 12.9M14.8 9.2 8.1 15.9M8.1 8.1l5.6 5.6"/>'),
        "razor": ("Razor", '<path d="M5 5h14l-2-3H7L5 5Z"/><path d="M7 5v3h10V5M12 8v11M9 21h6M10 19h4"/>'),
        "comb": ("Comb", '<path d="M4 5h16v5H4zM5 10v9m3-9v9m3-9v9m3-9v9m3-9v9m3-9v9"/>'),
        "hair-dryer": ("Hair dryer", '<path d="M4 7h11a5 5 0 0 1 0 10H9l-4 4v-4a5 5 0 0 1-1-10Z"/><path d="M15 9h5M15 15h5M10 17l2 5"/><circle cx="9" cy="12" r="1"/>'),
        "shampoo": ("Shampoo", '<path d="M9 3h6M10 3v3h4V3M8 6h8l2 3v11a2 2 0 0 1-2 2h-8a2 2 0 0 1-2-2V9l2-3Z"/><path d="M6 11h12M10 15h4"/>'),
        "mirror": ("Mirror", '<path d="M12 2a7 7 0 0 0-7 7v2a7 7 0 0 0 14 0V9a7 7 0 0 0-7-7Z"/><path d="M12 18v4M8 22h8M9 6l6 7"/>'),
        "mustache": ("Mustache", '<path d="M12 9c-1-3-4-4-7-3-3 1-4 5-2 7 3 3 7 2 9-1 2 3 6 4 9 1 2-2 1-6-2-7-3-1-6 0-7 3Z"/><path d="M12 9v4"/>'),
        "beard": ("Beard", '<path d="M5 5c2-2 4-3 7-3s5 1 7 3v5c0 6-3 10-7 12-4-2-7-6-7-12V5Z"/><path d="M8 11c1 1 2 1 4 1s3 0 4-1M8 15c1 2 2 3 4 4 2-1 3-2 4-4"/>'),
        "chair": ("Barber chair", '<path d="M7 3h10l2 7H5l2-7ZM5 12h14l2 5H3l2-5ZM12 17v4m-5 1h10m-8-1-2 1m8-1 2 1"/><path d="M7 10v2m10-2v2"/>'),
        "towel": ("Towel", '<path d="M5 3h14v18H5z"/><path d="M5 7h14M5 17h14M9 7v10m6-10v10"/>'),
        "spray": ("Spray bottle", '<path d="M9 3h6v3H9zM10 6v2H7l-2 3v9a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-9l-3-3h-2"/><path d="M19 9h3v2h-3M8 14h8M8 17h5"/>'),
        "barber-pole": ("Barber pole", '<path d="M9 2h6v3H9zM7 5h10v15H7zM9 20h6v2H9z"/><path d="m7 9 10 4M7 14l10 4M7 7l5 5m-5 6 10-4"/>'),
        "scissors-open": ("Open scissors", '<circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M8.1 8.1 20 20M8.1 15.9 20 4M11 11l3 3"/>'),
        "hair-clip": ("Hair clip", '<path d="M4 8h16l-2 8H6L4 8Z"/><path d="M7 8l3 8m1-8 2 8m1-8 2 8M8 4h8l2 4H6l2-4Z"/>'),
        "brush": ("Barber brush", '<path d="M10 3h4v10h-4zM8 13h8l2 8H6l2-8Z"/><path d="M8 17h8M9 6H7m10 0h-2M9 9H7m10 0h-2"/>'),
        "beard-trimmer": ("Beard trimmer", '<path d="M8 3h8v4H8zM9 7v12a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2V7"/><path d="M7 3v4m3-5v5m4-5v5m3-4v4M10 12h4m-4 3h4"/>'),
    },
    "actions": {
        "edit": ("Edit", '<path d="M12 20h9"/><path d="m16.5 3.5 4 4L9 19l-5 1 1-5L16.5 3.5Z"/>'),
        "delete": ("Delete", '<path d="M3 6h18M8 6V4h8v2m3 0-1 14H6L5 6m4 4v6m6-6v6"/>'),
        "add": ("Add", '<circle cx="12" cy="12" r="9"/><path d="M12 8v8m-4-4h8"/>'),
        "check": ("Check", '<path d="m5 12 4 4L19 6"/>'),
        "close": ("Close", '<path d="m6 6 12 12M18 6 6 18"/>'),
        "save": ("Save", '<path d="M5 3h12l4 4v14H3V3h2Z"/><path d="M7 3v6h10V3M7 21v-8h10v8"/>'),
        "cancel": ("Cancel", '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/>'),
        "view": ("View", '<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>'),
        "print": ("Print", '<path d="M7 8V3h10v5M7 17H5a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M7 14h10v7H7zM17 11h.01"/>'),
        "share": ("Share", '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.7 10.7 6.6-4.4m-6.6 9.4 6.6 4.4"/>'),
        "download": ("Download", '<path d="M12 3v12m-5-5 5 5 5-5M4 17v4h16v-4"/>'),
        "upload": ("Upload", '<path d="M12 16V4m-5 5 5-5 5 5M4 17v4h16v-4"/>'),
        "search": ("Search", '<circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/>'),
        "filter": ("Filter", '<path d="M3 5h18l-7 8v6l-4 2v-8L3 5Z"/>'),
        "sort": ("Sort", '<path d="M8 5v14m-4-4 4 4 4-4M16 19V5m-4 4 4-4 4 4"/>'),
        "refresh": ("Refresh", '<path d="M20 7v5h-5M4 17v-5h5"/><path d="M5.6 9A7 7 0 0 1 18 6l2 6M4 12l2 6a7 7 0 0 0 12.4-3"/>'),
        "copy": ("Copy", '<rect x="8" y="8" width="12" height="13" rx="2"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h3"/>'),
    },
    "navigation": {
        "dashboard": ("Dashboard", '<rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="5" rx="1.5"/><rect x="13" y="10" width="8" height="11" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/>'),
        "calendar": ("Calendar", '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18m-13 4h2m4 0h2m-8 4h2"/>'),
        "customers": ("Customers", '<circle cx="9" cy="8" r="3"/><path d="M3 20v-1a6 6 0 0 1 12 0v1H3Zm12-9a3 3 0 1 0-1-5.8M18 14a5 5 0 0 1 3 5v1h-3"/>'),
        "services": ("Services", '<path d="M14 6 8 12m8-9a4 4 0 0 0-5 5l-7 7a2 2 0 0 0 3 3l7-7a4 4 0 0 0 5-5l-3 3-3-3 3-3Z"/>'),
        "providers": ("Providers", '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2H4Z"/><path d="m9 14 3 3 3-3"/>'),
        "payments": ("Payments", '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4m6 0h1"/>'),
        "reports": ("Reports", '<path d="M4 20V9m5 11V4m5 16v-7m5 7V7m-17 14h20"/>'),
        "settings": ("Settings", '<circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1 1.2 2.1-2 2-2.2-1.2-.2.1-2.4 1v2h-3v-2l-2.4-1-.2-.1-2.2 1.2-2-2 1.2-2.1.1-.2-1-2.4H2v-3h2l1-2.4-.1-.2L3.7 6l2-2 2.2 1.2.2-.1 2.4-1V2h3v2l2.4 1 .2.1L18.3 4l2 2-1.2 2.1-.1.2 1 2.4h2v3h-2l-1 2.3Z"/>'),
        "logout": ("Log out", '<path d="M10 17l5-5-5-5m5 5H3"/><path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/>'),
        "home": ("Home", '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-6v-7h-4v7H4a1 1 0 0 1-1-1V10Z"/>'),
        "menu": ("Menu", '<path d="M4 6h16M4 12h16M4 18h16"/>'),
        "back": ("Back", '<path d="m15 18-6-6 6-6M9 12h12"/>'),
        "forward": ("Forward", '<path d="m9 18 6-6-6-6m6 6H3"/>'),
        "notifications": ("Notifications", '<path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4"/>'),
        "help": ("Help", '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 4.3 1.8c-1.1 1.1-1.8 1.4-1.8 3.2m0 3h.01"/>'),
        "booking": ("Booking", '<rect x="4" y="4" width="16" height="17" rx="2"/><path d="M8 2v4m8-4v4M4 9h16m-12 4h2m4 0h2m-8 4h2"/>'),
        "clock": ("Clock", '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'),
    },
    "status": {
        "pending": ("Pending", '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'),
        "confirmed": ("Confirmed", '<circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/>'),
        "completed": ("Completed", '<path d="m5 12 4 4L19 6"/><circle cx="12" cy="12" r="10"/>'),
        "cancelled": ("Cancelled", '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/>'),
        "no-show": ("No show", '<circle cx="9" cy="8" r="3"/><path d="M3 20v-1a6 6 0 0 1 9.5-4.8M16 16l5 5m0-5-5 5"/>'),
        "active": ("Active", '<circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/>'),
        "inactive": ("Inactive", '<circle cx="12" cy="12" r="9"/><path d="M8 12h8"/>'),
        "success": ("Success", '<path d="m5 12 4 4L19 6"/><circle cx="12" cy="12" r="10"/>'),
        "warning": ("Warning", '<path d="M10.3 4.2 2.5 18a2 2 0 0 0 1.7 3h15.6a2 2 0 0 0 1.7-3L13.7 4.2a2 2 0 0 0-3.4 0Z"/><path d="M12 9v5m0 3h.01"/>'),
        "danger": ("Danger", '<path d="M12 3 2.5 20h19L12 3Z"/><path d="M12 9v5m0 3h.01"/>'),
        "info": ("Information", '<circle cx="12" cy="12" r="9"/><path d="M12 11v5m0-8h.01"/>'),
        "toggle-on": ("Enabled", '<rect x="2" y="6" width="20" height="12" rx="6"/><circle cx="16" cy="12" r="4"/>'),
        "toggle-off": ("Disabled", '<rect x="2" y="6" width="20" height="12" rx="6"/><circle cx="8" cy="12" r="4"/>'),
        "verified": ("Verified", '<path d="m12 22-2.5-1.3L7 21l-1.2-2.5L3 17l.5-2.8L2 12l1.5-2.2L3 7l2.8-1.5L7 3l2.5.3L12 2l2.5 1.3L17 3l1.2 2.5L21 7l-.5 2.8L22 12l-1.5 2.2L21 17l-2.8 1.5L17 21l-2.5-.3L12 22Z"/><path d="m8 12 2.5 2.5L16 9"/>'),
        "progress": ("In progress", '<path d="M21 12a9 9 0 1 1-2.6-6.4"/><path d="M21 4v6h-6"/>'),
        "expired": ("Expired", '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2m4-10 2 2"/>'),
    },
}


def svg_document(title: str, body: str) -> str:
    return (
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" role="img" '
        'aria-labelledby="icon-title" fill="none" stroke="currentColor" stroke-width="1.5" '
        'stroke-linecap="round" stroke-linejoin="round">\n'
        f'  <title id="icon-title">{escape(title)}</title>\n  {body}\n</svg>\n'
    )


def main() -> None:
    symbols = []
    total = 0
    for category, icons in ICONS.items():
        directory = ROOT / category
        directory.mkdir(parents=True, exist_ok=True)
        for name, (title, body) in icons.items():
            (directory / f"{name}.svg").write_text(svg_document(title, body), encoding="utf-8")
            symbols.append(f'<symbol id="{category}-{name}" viewBox="0 0 24 24">{body}</symbol>')
            total += 1
    sprite = '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true">\n' + "\n".join(symbols) + "\n</svg>\n"
    (ROOT / "sprite.svg").write_text(sprite, encoding="utf-8")
    (ROOT / "README.md").write_text(
        "# Easy!Appointments barber line icons\n\n"
        f"{total} hand-drawn 24×24 line icons, grouped by task. Use inline SVG `<use href=\"/assets/img/icons/sprite.svg#barber-shop-scissors\">` to inherit `currentColor`, "
        "or reference a standalone SVG. Stroke width is 1.5 with rounded caps and joins.\n\n"
        "The SVG source is generated by `python3 scripts/generate_barber_icons.py`. Each standalone icon has a title for assistive technology; "
        "for decorative use, the consuming element should set `aria-hidden=\"true\"`.\n",
        encoding="utf-8",
    )
    print(f"Generated {total} icons in {ROOT}")


if __name__ == "__main__":
    main()
