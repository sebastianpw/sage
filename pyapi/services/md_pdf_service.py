# pyapi/services/md_pdf_service.py
"""
SAGE Markdown to PDF Service
---------------------------------
Converts markdown on the fly directly to a downloadable PDF stream without saving to disk.
Uses the same shared fonts and ReportLab integration from the magazine module.
Includes a native, lightweight hand-rolled Markdown to ReportLab state machine parser 
capable of handling Tables, Horizontal Rules, and Page Breaks natively.
"""

import io
import re
import logging
import urllib.request
from pathlib import Path
from typing import Optional

from fastapi import APIRouter, Form, HTTPException
from fastapi.responses import StreamingResponse

from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, Flowable, PageBreak
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.pagesizes import A4, portrait, landscape
from reportlab.lib.units import mm
from reportlab.lib import colors

from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.lib.fonts import addMapping

logger = logging.getLogger(__name__)
router = APIRouter()

# ---------------------------------------------------------------------------
# Font setup (cloned safely from magazine pdf logic)
# ---------------------------------------------------------------------------
_FONT_DIR = Path(__file__).resolve().parent.parent / "assets" / "fonts"
_FONT_URLS = {
    "Cinzel.ttf":        "https://raw.githubusercontent.com/google/fonts/main/ofl/cinzel/Cinzel%5Bwght%5D.ttf",
    "Lora.ttf":          "https://raw.githubusercontent.com/google/fonts/main/ofl/lora/Lora%5Bwght%5D.ttf",
    "Lora-Italic.ttf":   "https://raw.githubusercontent.com/google/fonts/main/ofl/lora/Lora-Italic%5Bwght%5D.ttf",
}

def _ensure_fonts() -> bool:
    _FONT_DIR.mkdir(parents=True, exist_ok=True)
    all_ok = True
    for filename, url in _FONT_URLS.items():
        dest = _FONT_DIR / filename
        if dest.exists() and dest.stat().st_size > 10_000:
            continue
        try:
            req = urllib.request.Request(url, headers={"User-Agent": "curl/7.0"})
            with urllib.request.urlopen(req, timeout=30) as resp:
                data = resp.read()
            dest.write_bytes(data)
        except Exception as exc:
            logger.warning("Could not download font %s: %s", filename, exc)
            all_ok = False
    return all_ok

def _register_fonts() -> bool:
    if "Cinzel" in pdfmetrics.getRegisteredFontNames():
        return True

    if not _ensure_fonts():
        return False

    try:
        cinzel_path      = str(_FONT_DIR / "Cinzel.ttf")
        lora_path        = str(_FONT_DIR / "Lora.ttf")
        lora_italic_path = str(_FONT_DIR / "Lora-Italic.ttf")

        pdfmetrics.registerFont(TTFont("Cinzel",          cinzel_path))
        pdfmetrics.registerFont(TTFont("Cinzel-Bold",     cinzel_path))
        pdfmetrics.registerFont(TTFont("Lora",            lora_path))
        pdfmetrics.registerFont(TTFont("Lora-Bold",       lora_path))
        pdfmetrics.registerFont(TTFont("Lora-Italic",     lora_italic_path))
        pdfmetrics.registerFont(TTFont("Lora-BoldItalic", lora_italic_path))

        addMapping("Cinzel", 0, 0, "Cinzel")
        addMapping("Cinzel", 1, 0, "Cinzel-Bold")
        addMapping("Lora",   0, 0, "Lora")
        addMapping("Lora",   1, 0, "Lora-Bold")
        addMapping("Lora",   0, 1, "Lora-Italic")
        addMapping("Lora",   1, 1, "Lora-BoldItalic")

        # Load comic fonts as well 
        custom_fonts_dir = Path(__file__).resolve().parent.parent / "fonts"
        comic_fonts = {
            "Bangers": "Bangers-Regular.ttf",
            "PermanentMarker": "PermanentMarker-Regular.ttf",
            "Oswald": "Oswald-Bold.ttf",
            "SpaceMono": "SpaceMono-Regular.ttf"
        }
        for family_name, file_name in comic_fonts.items():
            font_path = custom_fonts_dir / file_name
            if font_path.exists():
                pdfmetrics.registerFont(TTFont(family_name, str(font_path)))
                addMapping(family_name, 0, 0, family_name)

        return True
    except Exception as exc:
        logger.warning("Font registration failed: %s", exc)
        return False


def _parse_markup(text: str) -> str:
    """State machine to guarantee perfectly nested XML tags for ReportLab."""
    stack = []
    out = []
    i = 0
    while i < len(text):
        # Check 3 chars (***, ___)
        if i + 2 < len(text) and text[i:i+3] in ('***', '___'):
            # Ignore underscores inside words natively (e.g. PI1_BASTIEN)
            if text[i] == '_' and i > 0 and text[i-1].isalnum() and i+3 < len(text) and text[i+3].isalnum():
                out.append(text[i:i+3])
                i += 3
                continue
                
            if 'b' in stack and 'i' in stack:
                while stack: out.append(f'</{stack.pop()}>')
            elif 'b' in stack:
                out.append('</b><i>')
                stack.remove('b')
                stack.append('i')
            elif 'i' in stack:
                out.append('</i><b>')
                stack.remove('i')
                stack.append('b')
            else:
                out.append('<b><i>')
                stack.append('b')
                stack.append('i')
            i += 3

        # Check 2 chars (**, __)
        elif i + 1 < len(text) and text[i:i+2] in ('**', '__'):
            if text[i] == '_' and i > 0 and text[i-1].isalnum() and i+2 < len(text) and text[i+2].isalnum():
                out.append(text[i:i+2])
                i += 2
                continue
                
            if 'b' in stack:
                # If bold is not on top, safely weave tags so XML stays valid
                if stack[-1] != 'b':
                    out.append('</i></b><i>')
                    stack.remove('b')
                else:
                    out.append('</b>')
                    stack.pop()
            else:
                out.append('<b>')
                stack.append('b')
            i += 2

        # Check 1 char (*, _)
        elif text[i] in ('*', '_'):
            if text[i] == '_' and i > 0 and text[i-1].isalnum() and i+1 < len(text) and text[i+1].isalnum():
                out.append(text[i])
                i += 1
                continue
                
            if 'i' in stack:
                if stack[-1] != 'i':
                    out.append('</b></i><b>')
                    stack.remove('i')
                else:
                    out.append('</i>')
                    stack.pop()
            else:
                out.append('<i>')
                stack.append('i')
            i += 1
        else:
            out.append(text[i])
            i += 1

    # Safely close any left-over open tags
    while stack:
        out.append(f'</{stack.pop()}>')

    return "".join(out)


def format_inline(text: str) -> str:
    """Parses inline markdown into ReportLab HTML-like tags, safely extracting code/links."""
    # 1. Escape HTML first
    text = text.replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;')
    
    placeholders = {}
    ph_idx = 0
    
    # 2. Extract and protect Inline Code
    # Use alphanumeric placeholders to avoid triggering underscore/asterisk markup parser
    def code_repl(m):
        nonlocal ph_idx
        key = f"CODEPLACEHOLDER{ph_idx}X"
        ph_idx += 1
        placeholders[key] = f'<font name="Courier">{m.group(1)}</font>'
        return key
        
    text = re.sub(r'`(.+?)`', code_repl, text)
    
    # 3. Extract and protect URLs
    url_placeholders = {}
    def url_protect(m):
        nonlocal ph_idx
        key = f"URLPLACEHOLDER{ph_idx}X"
        ph_idx += 1
        url_placeholders[key] = m.group(2)
        return f'[{m.group(1)}]({key})'
        
    text = re.sub(r'\[(.*?)\]\((.*?)\)', url_protect, text)
    
    # 4. Safely apply bold/italics using the state machine (Never outputs overlapping XML)
    text = _parse_markup(text)
    
    # 5. Restore URLs and convert to ReportLab <a> tags
    def url_restore(m):
        link_text = m.group(1)
        key = m.group(2)
        href = url_placeholders.get(key, key)
        return f'<a href="{href}" color="blue">{link_text}</a>'
        
    text = re.sub(r'\[(.*?)\]\((URLPLACEHOLDER\d+X)\)', url_restore, text)
    
    # 6. Restore Inline Code blocks
    for key, val in placeholders.items():
        text = text.replace(key, val)
        
    return text


# Custom Flowable for Horizontal Rules (---)
class HRFlowable(Flowable):
    def __init__(self, thickness=1, color=colors.HexColor('#d0d7de'), spaceBefore=10, spaceAfter=10):
        Flowable.__init__(self)
        self.thickness = thickness
        self.color = color
        self.spaceBefore = spaceBefore
        self.spaceAfter = spaceAfter

    def wrap(self, availWidth, availHeight):
        self.availWidth = availWidth
        return availWidth, self.spaceBefore + self.spaceAfter + self.thickness

    def draw(self):
        self.canv.saveState()
        self.canv.setStrokeColor(self.color)
        self.canv.setLineWidth(self.thickness)
        y = self.spaceAfter + (self.thickness / 2.0)
        self.canv.line(0, y, self.availWidth, y)
        self.canv.restoreState()


# ---------------------------------------------------------------------------
# Endpoint
# ---------------------------------------------------------------------------
@router.post("/export")
async def export_md_to_pdf(
    markdown_text: str = Form(""),
    page_size: str = Form("A4"),
    orientation: str = Form("portrait"),
    font_family: str = Form("Helvetica"),
    font_size: int = Form(12),
    title: str = Form("Document")
):
    _register_fonts()
    
    # 1. Page Size & Orientation configuration
    size = A4
    if orientation.lower() == "landscape":
        size = landscape(size)
    else:
        size = portrait(size)
        
    buffer = io.BytesIO()
    
    # 2. Simple template with margin/padding
    margin = 20 * mm
    doc = SimpleDocTemplate(
        buffer,
        pagesize=size,
        title=title,
        leftMargin=margin, rightMargin=margin,
        topMargin=margin, bottomMargin=margin
    )
    
    styles = getSampleStyleSheet()
    
    # Validation fallback if requested font is not found
    if font_family not in pdfmetrics.getRegisteredFontNames() and font_family not in ["Helvetica", "Times-Roman", "Courier"]:
        font_family = "Helvetica"
        
    style_normal = ParagraphStyle(
        'CustomNormal',
        parent=styles['Normal'],
        fontName=font_family,
        fontSize=font_size,
        leading=font_size * 1.5,
        spaceAfter=font_size
    )
    
    style_h1 = ParagraphStyle('CustomH1', parent=style_normal, fontSize=font_size * 2, leading=font_size * 2.4, spaceAfter=font_size, spaceBefore=font_size)
    style_h2 = ParagraphStyle('CustomH2', parent=style_normal, fontSize=font_size * 1.5, leading=font_size * 1.8, spaceAfter=font_size, spaceBefore=font_size)
    style_h3 = ParagraphStyle('CustomH3', parent=style_normal, fontSize=font_size * 1.25, leading=font_size * 1.5, spaceAfter=font_size, spaceBefore=font_size)
    style_quote = ParagraphStyle('CustomQuote', parent=style_normal, leftIndent=20, textColor=colors.HexColor("#555555"), fontName=font_family)
    
    story = []
    
    # 3. Parse Markdown robustly line-by-line using a state machine
    lines = markdown_text.replace('\r\n', '\n').split('\n')
    
    current_paragraph = []
    in_code_block = False
    code_lines = []
    
    in_table = False
    table_rows = []

    def flush_paragraph():
        if current_paragraph:
            text = ' '.join(current_paragraph)
            story.append(Paragraph(format_inline(text), style_normal))
            current_paragraph.clear()

    def flush_table():
        if not table_rows: return
        
        # Validate if it's actually a table (has a separator row)
        has_separator = False
        for row in table_rows:
            # Match Markdown table separator line eg. |---|:---|
            if re.match(r'^[\|\-\:\s]+$', row) and '-' in row:
                has_separator = True
                break
                
        if not has_separator or len(table_rows) < 2:
            # False alarm (just a regular text line with | symbol), render as normal paragraphs
            for row in table_rows:
                story.append(Paragraph(format_inline(row), style_normal))
            table_rows.clear()
            return
            
        # Build ReportLab Table
        cleaned_rows = []
        for row in table_rows:
            row = row.strip()
            # Skip separator row visually
            if re.match(r'^[\|\-\:\s]+$', row) and '-' in row:
                continue 
                
            if row.startswith('|'): row = row[1:]
            if row.endswith('|'): row = row[:-1]
            
            cells = [Paragraph(format_inline(c.strip()), style_normal) for c in row.split('|')]
            cleaned_rows.append(cells)
            
        if cleaned_rows:
            t = Table(cleaned_rows, hAlign='LEFT')
            t.setStyle(TableStyle([
                ('BACKGROUND', (0,0), (-1,0), colors.HexColor('#f6f8fa')), # Header bg
                ('ALIGN', (0,0), (-1,-1), 'LEFT'),
                ('VALIGN', (0,0), (-1,-1), 'TOP'),
                ('BOTTOMPADDING', (0,0), (-1,0), 8),
                ('TOPPADDING', (0,0), (-1,-1), 8),
                ('GRID', (0,0), (-1,-1), 1, colors.HexColor('#d0d7de')) # Borders
            ]))
            story.append(t)
            story.append(Spacer(1, font_size))
        table_rows.clear()

    for line in lines:
        stripped = line.strip()
        
        # State machine check: Table exit condition
        if in_table:
            if '|' in stripped:
                table_rows.append(stripped)
                continue
            else:
                flush_table()
                in_table = False
                
        # State machine check: Code Block
        if stripped.startswith('```'):
            if in_code_block:
                in_code_block = False
                code_text = '\n'.join(code_lines)
                code_text = code_text.replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;')
                code_text = code_text.replace('\n', '<br/>')
                story.append(Paragraph(f'<font name="Courier">{code_text}</font>', style_normal))
                code_lines = []
            else:
                flush_paragraph()
                in_code_block = True
            continue
            
        if in_code_block:
            code_lines.append(line) # Keep original line for internal code indentation
            continue
            
        # Empty Line breaks paragraphs
        if not stripped:
            flush_paragraph()
            continue
            
        # Page Break (***)
        if re.match(r'^\*{3,}$', stripped):
            flush_paragraph()
            story.append(PageBreak())
            continue
            
        # Horizontal Rules (---, ___)
        if re.match(r'^[-_]{3,}$', stripped):
            flush_paragraph()
            story.append(HRFlowable(spaceBefore=font_size, spaceAfter=font_size))
            continue
            
        # Table Trigger Entry
        if '|' in stripped and not stripped.startswith('#') and not stripped.startswith('>'):
            flush_paragraph()
            in_table = True
            table_rows.append(stripped)
            continue
            
        # Headers
        header_match = re.match(r'^(#{1,6})\s+(.*)', stripped)
        if header_match:
            flush_paragraph()
            level = len(header_match.group(1))
            content = format_inline(header_match.group(2))
            if level == 1:
                story.append(Paragraph(f"<b>{content}</b>", style_h1))
            elif level == 2:
                story.append(Paragraph(f"<b>{content}</b>", style_h2))
            else:
                story.append(Paragraph(f"<b>{content}</b>", style_h3))
            continue
            
        # Blockquotes
        if stripped.startswith('>'):
            flush_paragraph()
            content = format_inline(stripped.lstrip('>').strip())
            story.append(Paragraph(f"<i>{content}</i>", style_quote))
            continue
            
        # Unordered Lists
        ul_match = re.match(r'^[\-\*\+]\s+(.*)', stripped)
        if ul_match:
            flush_paragraph()
            content = format_inline(ul_match.group(1))
            story.append(Paragraph(f"&bull; {content}", style_normal))
            continue
            
        # Ordered Lists
        ol_match = re.match(r'^(\d+)\.\s+(.*)', stripped)
        if ol_match:
            flush_paragraph()
            num = ol_match.group(1)
            content = format_inline(ol_match.group(2))
            story.append(Paragraph(f"{num}. {content}", style_normal))
            continue
            
        # Standard paragraph text
        current_paragraph.append(stripped)

    # Flush any remaining buffers at EOF
    flush_paragraph()
    if in_table:
        flush_table()
    
    # If the story is completely empty, add a space to prevent ReportLab crash
    if not story:
        story.append(Paragraph(" ", style_normal))
        
    doc.build(story)
    buffer.seek(0)
    
    # Ensure safe filename for headers
    safe_title = "".join([c for c in title if c.isalpha() or c.isdigit() or c==' ']).rstrip()
    filename = f"{safe_title.replace(' ', '_')}.pdf" if safe_title else "export.pdf"
    
    return StreamingResponse(
        buffer,
        media_type="application/pdf",
        headers={"Content-Disposition": f'attachment; filename="{filename}"'}
    )
