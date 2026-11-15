#!/usr/bin/env python3
"""Crée des PDF de démonstration (vrais PDF, lisibles dans le navigateur) dans docs/pdf_demo/."""
import os

def echapper(t):
    return t.replace("\\", "\\\\").replace("(", "\\(").replace(")", "\\)")

def creer_pdf(chemin, titre, lignes):
    flux = ["BT", "/F1 18 Tf", "50 780 Td", "(" + echapper(titre) + ") Tj", "/F1 12 Tf"]
    for l in lignes:
        flux += ["0 -28 Td", "(" + echapper(l) + ") Tj"]
    flux.append("ET")
    contenu = "\n".join(flux).encode("cp1252")
    objets = [
        b"<< /Type /Catalog /Pages 2 0 R >>",
        b"<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
        b"<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 5 0 R /Resources << /Font << /F1 4 0 R >> >> >>",
        b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>",
        b"<< /Length " + str(len(contenu)).encode() + b" >>\nstream\n" + contenu + b"\nendstream",
    ]
    sortie = bytearray(b"%PDF-1.4\n")
    positions = []
    for i, o in enumerate(objets, start=1):
        positions.append(len(sortie))
        sortie += str(i).encode() + b" 0 obj\n" + o + b"\nendobj\n"
    debut_xref = len(sortie)
    sortie += b"xref\n0 " + str(len(objets) + 1).encode() + b"\n0000000000 65535 f \n"
    for p in positions:
        sortie += ("%010d 00000 n \n" % p).encode()
    sortie += b"trailer\n<< /Size " + str(len(objets) + 1).encode() + b" /Root 1 0 R >>\nstartxref\n" + str(debut_xref).encode() + b"\n%%EOF\n"
    with open(chemin, "wb") as f:
        f.write(sortie)

dossier = os.path.join(os.path.dirname(os.path.abspath(__file__)), "pdf_demo")
os.makedirs(dossier, exist_ok=True)

creer_pdf(os.path.join(dossier, "bordereau_oct2026.pdf"), "BORDEREAU - Octobre 2026 (document de test)", [
    "Emetteur : Fournisseur de démonstration",
    "NIF émetteur : 5000000022",
    "Date du document : 12/10/2026",
    "Montant TTC : 650 000 Ar",
])
creer_pdf(os.path.join(dossier, "facture_fournisseur.pdf"), "FACTURE F-2026-081 (document de test)", [
    "Emetteur : Fournisseur de démonstration",
    "NIF émetteur : 5000000033",
    "Date du document : 10/08/2026",
    "Montant TTC : 480 000 Ar",
])
creer_pdf(os.path.join(dossier, "bordereau_irsa_oct2026.pdf"), "BORDEREAU IRSA - Octobre 2026 (document de test)", [
    "Emetteur : Société de démonstration",
    "NIF émetteur : 5000000044",
    "Date du document : 31/10/2026",
    "Montant TTC : 1 250 000 Ar",
])
with open(os.path.join(dossier, "faux.pdf"), "w") as f:
    f.write("ceci n'est pas un PDF\n")
print("PDF créés dans", dossier)
