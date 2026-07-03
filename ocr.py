import pytesseract
from PIL import Image

# Tentukan lokasi tesseract.exe
pytesseract.pytesseract.tesseract_cmd = r"C:\Program Files\Tesseract-OCR\tesseract.exe"

# Cek versi
print("Versi Tesseract:", pytesseract.get_tesseract_version())

# (Opsional) Coba baca teks dari gambar
img = Image.open("contoh_gambar.jpg")  # ganti dengan nama file kamu
text = pytesseract.image_to_string(img)
print(text)
