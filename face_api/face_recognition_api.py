#!/usr/bin/env python3
"""
UniMind - API Reconnaissance Faciale
Utilise DeepFace (compatible Python 3.13)
"""

from flask import Flask, request, jsonify
from flask_cors import CORS
import base64, os, logging
import numpy as np
from PIL import Image
import io, cv2

app = Flask(__name__)
CORS(app)

BASE_DIR   = os.path.dirname(os.path.abspath(__file__))
TEMP_DIR   = os.path.join(BASE_DIR, "temp")
PHOTOS_DIR = r"C:\Users\ISLEM\Downloads\unimind-main (2)\unimind-main\unimind-main\public\uploads\photos"
os.makedirs(TEMP_DIR, exist_ok=True)

logging.basicConfig(level=logging.INFO, format='%(asctime)s [%(levelname)s] %(message)s')
logger = logging.getLogger(__name__)

try:
    from deepface import DeepFace
    DEEPFACE_OK = True
    logger.info("✅ DeepFace chargé")
except ImportError:
    DEEPFACE_OK = False
    logger.error("❌ DeepFace manquant - pip install deepface tf-keras opencv-python")

def decode_b64(b64):
    if "," in b64: b64 = b64.split(",")[1]
    arr = np.array(Image.open(io.BytesIO(base64.b64decode(b64))).convert("RGB"))
    return cv2.cvtColor(arr, cv2.COLOR_RGB2BGR)

def save_temp(img, name):
    p = os.path.join(TEMP_DIR, name)
    cv2.imwrite(p, img)
    return p

def del_temp(p):
    try:
        if p and os.path.exists(p): os.remove(p)
    except: pass

@app.route("/health")
def health():
    n = len([f for f in os.listdir(PHOTOS_DIR) if os.path.exists(PHOTOS_DIR) and f.lower().endswith(('.jpg','.jpeg','.png'))]) if os.path.exists(PHOTOS_DIR) else 0
    return jsonify({"status":"ok" if DEEPFACE_OK else "degraded","deepface":DEEPFACE_OK,"photos_dir":PHOTOS_DIR,"photos_dir_exists":os.path.exists(PHOTOS_DIR),"photos_count":n})

@app.route("/api/face/test")
def test():
    photos = [f for f in os.listdir(PHOTOS_DIR) if f.lower().endswith(('.jpg','.jpeg','.png','.webp'))] if os.path.exists(PHOTOS_DIR) else []
    return jsonify({"deepface_ok":DEEPFACE_OK,"photos_dir":PHOTOS_DIR,"photos_dir_exists":os.path.exists(PHOTOS_DIR),"photos":photos[:20],"photos_total":len(photos)})

@app.route("/api/face/encode", methods=["POST"])
def encode():
    if not DEEPFACE_OK: return jsonify({"valid":True,"message":"Ignoré"})
    data = request.get_json() or {}
    fn = data.get("photo_filename")
    if not fn: return jsonify({"error":"photo_filename manquant"}),400
    p = os.path.join(PHOTOS_DIR, fn)
    if not os.path.exists(p): return jsonify({"valid":False,"message":"Fichier introuvable"}),404
    try:
        faces = DeepFace.extract_faces(img_path=p, detector_backend="opencv", enforce_detection=False)
        return jsonify({"valid":True,"faces_detected":len(faces),"message":"Visage détecté ✅"})
    except:
        return jsonify({"valid":False,"message":"Aucun visage dans cette photo."})

@app.route("/api/face/verify", methods=["POST"])
def verify():
    if not DEEPFACE_OK: return jsonify({"error":"DeepFace non installé"}),503
    data = request.get_json() or {}
    b64  = data.get("captured_image")
    pf   = data.get("user_photo")
    uid  = data.get("user_id","?")
    if not b64: return jsonify({"error":"captured_image manquant"}),400
    if not pf:  return jsonify({"error":"user_photo manquant"}),400
    pp = os.path.join(PHOTOS_DIR, pf)
    if not os.path.exists(pp): return jsonify({"match":False,"confidence":0.0,"message":f"Photo introuvable: {pf}"}),404
    tmp = None
    try:
        logger.info(f"🔍 Vérification user_id={uid}")
        tmp = save_temp(decode_b64(b64), f"cap_{uid}.jpg")
        r = DeepFace.verify(img1_path=tmp, img2_path=pp, model_name="VGG-Face", detector_backend="opencv", enforce_detection=False, distance_metric="cosine", silent=True)
        ok   = bool(r.get("verified",False))
        dist = float(r.get("distance",1.0))
        thr  = float(r.get("threshold",0.4))
        conf = max(0.0,min(1.0,1.0-(dist/thr)))
        logger.info(f"User {uid}: match={ok}, confidence={conf:.3f}")
        return jsonify({"match":ok,"confidence":round(conf,3),"message":"✅ Identité vérifiée !" if ok else "❌ Visage non reconnu."})
    except ValueError as e:
        msg = str(e)
        if any(k in msg for k in ["Face could not","No face","face could not"]):
            return jsonify({"match":False,"confidence":0.0,"message":"Aucun visage détecté. Soyez bien éclairé et face à la caméra."})
        return jsonify({"match":False,"confidence":0.0,"message":msg})
    except Exception as e:
        logger.error(f"Erreur: {e}")
        return jsonify({"error":str(e)}),500
    finally:
        del_temp(tmp)

if __name__ == "__main__":
    print("\n"+"="*55)
    print("  🚀  UniMind - API Reconnaissance Faciale (DeepFace)")
    print("="*55)
    print(f"  📁  Photos : {PHOTOS_DIR}")
    print(f"  📁  Existe : {os.path.exists(PHOTOS_DIR)}")
    print(f"  🤖  DeepFace: {'✅ OK' if DEEPFACE_OK else '❌ Manquant'}")
    print(f"  🌐  URL    : http://127.0.0.1:5000")
    print(f"  🔍  Test   : http://127.0.0.1:5000/api/face/test")
    print("="*55+"\n")
    app.run(host="0.0.0.0", port=5000, debug=False)
