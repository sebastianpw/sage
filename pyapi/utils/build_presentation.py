import re
import os
from pathlib import Path
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.platypus import BaseDocTemplate, Frame, PageTemplate, Paragraph, Spacer, Image as RLImage
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.enums import TA_LEFT, TA_CENTER, TA_JUSTIFY
from reportlab.lib import colors

# --- Copied directly from your magazine_pdf_service.py ---
def _markdown_to_reportlab(text: str) -> str:
    """
    Safely converts basic markdown (**bold**, *italic*, - bullets) 
    into ReportLab-compatible XML tags.
    """
    text = text.replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;')
    
    # Bold and Italic
    text = re.sub(r'\*\*(.+?)\*\*', r'<b>\1</b>', text)
    text = re.sub(r'__(.+?)__', r'<b>\1</b>', text)
    text = re.sub(r'\*(.+?)\*', r'<i>\1</i>', text)
    text = re.sub(r'_(.+?)_', r'<i>\1</i>', text)
    
    # Bullets
    text = re.sub(r'(?m)^[\-\*]\s+(.+)$', r'&bull; \1', text)
    
    # Linebreaks
    text = text.replace('\n', '<br/>')
    return text
# ---------------------------------------------------------

def build_pdf():
    md_file = Path("sage_presentation.md")
    pdf_file = Path("SAGE_Presentation.pdf")
    base_dir = Path(".").resolve()

    if not md_file.exists():
        print(f"❌ Error: Could not find {md_file.name}. Please make sure it's in the same folder.")
        return

    # 1. Background Painter Function
    def draw_black_bg(canvas, doc):
        canvas.saveState()
        # Deep dark background matching SAGE UI
        canvas.setFillColor(colors.HexColor("#0d0d11"))
        canvas.rect(0, 0, A4[0], A4[1], fill=1, stroke=0)
        canvas.restoreState()

    # 2. Setup Document with reduced margins (10mm)
    doc = BaseDocTemplate(
        str(pdf_file),
        pagesize=A4,
        leftMargin=10*mm, rightMargin=10*mm,
        topMargin=10*mm, bottomMargin=10*mm,
        title="SAGE Presentation",
        author="SAGE AI"
    )
    
    # Create the main layout frame
    frame = Frame(
        doc.leftMargin, doc.bottomMargin, 
        doc.width, doc.height, 
        id='normal',
        leftPadding=0, bottomPadding=0, rightPadding=0, topPadding=0
    )
    
    # Bind the frame and the background painter to the page template
    template = PageTemplate(id='main', frames=[frame], onPage=draw_black_bg)
    doc.addPageTemplates([template])

    # 3. Styles (White / Light text for dark mode)
    styles = getSampleStyleSheet()
    
    title_style = ParagraphStyle(
        'CustomTitle',
        parent=styles['Heading1'],
        fontName='Helvetica-Bold',
        fontSize=26,
        leading=32,
        alignment=TA_CENTER,
        spaceAfter=30,
        textColor=colors.HexColor("#FFFFFF") 
    )
    
    text_style = ParagraphStyle(
        'CustomText',
        parent=styles['Normal'],
        fontName='Helvetica',
        fontSize=11,
        leading=16,
        alignment=TA_JUSTIFY,
        spaceAfter=15,
        textColor=colors.HexColor("#E0E0E0") # Off-white for readability
    )
    
    timestamp_style = ParagraphStyle(
        'Timestamp',
        parent=text_style,
        fontName='Helvetica-Oblique',
        textColor=colors.HexColor("#888888"),
        alignment=TA_CENTER,
        spaceBefore=10,
        spaceAfter=10
    )

    story = []
    content = md_file.read_text(encoding='utf-8')
    
    # Regex to split the markdown by image tags
    pattern = r'!\[.*?\]\((.*?)\)'
    parts = re.split(pattern, content)
    
    # 4. Image Size Constraints (UPDATED)
    # Max width: 80% of available width 
    # Max height: 66% (two-thirds) of page height
    max_img_width = (A4[0] - 20*mm) * 0.80
    max_img_height = A4[1] * 0.66

    for i, part in enumerate(parts):
        if i % 2 == 0:
            # --- TEXT BLOCK ---
            if not part.strip():
                continue
                
            # Handle the main title specifically
            if i == 0 and part.startswith("# "):
                lines = part.strip().split('\n', 1)
                title_text = lines[0].replace('# ', '')
                story.append(Paragraph(title_text, title_style))
                if len(lines) > 1:
                    part = lines[1]
                else:
                    continue
            
            paragraphs = part.split('\n\n')
            for p in paragraphs:
                p = p.strip()
                if not p:
                    continue
                
                if p.startswith("*(Zeitstempel") or p.startswith("*(Timestamp"):
                    story.append(Paragraph(p, timestamp_style))
                else:
                    md_text = _markdown_to_reportlab(p)
                    story.append(Paragraph(md_text, text_style))
                    story.append(Spacer(1, 5))
                    
        else:
            # --- IMAGE BLOCK ---
            img_path_str = part.strip()
            img_path = Path(img_path_str)
            
            if not img_path.is_absolute():
                img_path = base_dir / img_path

            if img_path.exists():
                try:
                    img = RLImage(str(img_path))
                    
                    # Aspect Ratio scaling within the new constraints
                    scale = max_img_width / img.imageWidth
                    img.drawWidth = max_img_width
                    img.drawHeight = img.imageHeight * scale
                    
                    # If it's too tall, scale by height instead
                    if img.drawHeight > max_img_height:
                        scale = max_img_height / img.imageHeight
                        img.drawWidth = img.imageWidth * scale
                        img.drawHeight = max_img_height
                        
                    img.hAlign = 'CENTER'
                    
                    story.append(Spacer(1, 10))
                    story.append(img)
                    story.append(Spacer(1, 15))
                    
                except Exception as e:
                    print(f"⚠️ Error loading image {img_path.name}: {e}")
            else:
                print(f"⚠️ Warning: Image not found -> {img_path_str}")

    # Build the final PDF
    doc.build(story)
    print(f"✅ PDF successfully generated (Dark Mode, Larger Images): {pdf_file}")

if __name__ == "__main__":
    build_pdf()
