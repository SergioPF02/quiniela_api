import requests
import concurrent.futures
import random
import time
import string
import json

# CONFIGURACIÓN
BASE_URL = "http://localhost/quiniela_api/public/api"
NUM_USERS = 20
TIMESTAMP = int(time.time())

# Almacén de datos compartidos
active_tokens = []
valid_match_ids = []
created_leagues_codes = []

def get_random_string(length=8):
    return ''.join(random.choices(string.ascii_letters + string.digits, k=length))

def get_matches():
    """Obtiene IDs de partidos reales para usar en apuestas"""
    try:
        response = requests.get(f"{BASE_URL}/partidos")
        if response.status_code == 200:
            matches = response.json()
            ids = [m['id'] for m in matches]
            print(f"[SETUP] Se encontraron {len(ids)} partidos en el sistema.")
            return ids
        else:
            print(f"[SETUP] Error obteniendo partidos: {response.status_code}")
            return []
    except Exception as e:
        print(f"[SETUP] Excepción obteniendo partidos: {e}")
        return []

def simulate_user(user_id):
    """Ciclo de vida de un usuario simulado"""
    email = f"bot_{TIMESTAMP}_{user_id}@stress.test"
    password = "password123"
    name = f"Bot User {user_id}"
    token = None
    
    session = requests.Session()
    
    # 1. REGISTRO
    try:
        reg_payload = {
            "name": name,
            "email": email,
            "password": password
        }
        r = session.post(f"{BASE_URL}/register", json=reg_payload, timeout=10)
        
        if r.status_code == 200:
            data = r.json()
            token = data.get('access_token')
            # print(f"[User {user_id}] Registrado OK.")
        else:
            print(f"[User {user_id}] FALLO Registro: {r.status_code} - {r.text}")
            return # Abortar si no puede registrarse
            
    except Exception as e:
        print(f"[User {user_id}] EXCEPCIÓN en Registro: {e}")
        return

    headers = {"Authorization": f"Bearer {token}", "Accept": "application/json"}
    
    # 2. CONSULTAR DASHBOARD (Carga ligera de lectura)
    try:
        r = session.get(f"{BASE_URL}/dashboard/quinielas", headers=headers, timeout=5)
    except:
        pass

    # 3. CREAR LIGA PRIVADA (10% de probabilidad)
    if random.random() < 0.10 and len(valid_match_ids) > 0:
        try:
            # Seleccionar 5 partidos al azar
            selected_matches = random.sample(valid_match_ids, min(5, len(valid_match_ids)))
            payload = {
                "name": f"Liga Bot {user_id}",
                "match_ids": selected_matches
            }
            r = session.post(f"{BASE_URL}/private-leagues", json=payload, headers=headers, timeout=10)
            if r.status_code == 201:
                # print(f"[User {user_id}] Creó liga privada.")
                pass
            else:
                print(f"[User {user_id}] Error creando liga: {r.text}")
        except Exception as e:
            print(f"[User {user_id}] Error en Liga Privada: {e}")

    # 4. APOSTAR / PRONOSTICAR (Bulk) - La operación más pesada
    if len(valid_match_ids) > 0:
        try:
            # Pronosticar en 10 partidos aleatorios
            matches_to_predict = random.sample(valid_match_ids, min(10, len(valid_match_ids)))
            predictions = []
            for mid in matches_to_predict:
                predictions.append({
                    "match_id": mid,
                    "home_score": random.randint(0, 3),
                    "away_score": random.randint(0, 3)
                })
            
            payload = {
                "predictions": predictions,
                "quiniela_id": None, # Simula apuesta global o mix
                "private_league_id": None
            }
            
            start_time = time.time()
            r = session.post(f"{BASE_URL}/pronosticar/bulk", json=payload, headers=headers, timeout=15)
            duration = time.time() - start_time
            
            if r.status_code == 200:
                pass # Éxito silencioso para no saturar log
            else:
                print(f"[User {user_id}] FALLO Apuesta: {r.status_code} - {r.text}")
                
            if duration > 2.0:
                print(f"[WARNING] [User {user_id}] La apuesta tardó {duration:.2f} segundos.")
                
        except Exception as e:
            print(f"[User {user_id}] EXCEPCIÓN en Apuesta: {e}")

    # 5. VER PARTIDOS DESTACADOS (Lectura final)
    try:
        session.get(f"{BASE_URL}/partidos/destacados", headers=headers, timeout=5)
    except:
        pass

def main():
    print(f"--- INICIANDO STRESS TEST CON {NUM_USERS} USUARIOS ---")
    
    global valid_match_ids
    valid_match_ids = get_matches()
    
    if not valid_match_ids:
        print("No se encontraron partidos. Asegúrate de correr las migraciones y seeders.")
        return

    print("Disparando hilos...")
    start_all = time.time()
    
    with concurrent.futures.ThreadPoolExecutor(max_workers=NUM_USERS) as executor:
        futures = [executor.submit(simulate_user, i) for i in range(NUM_USERS)]
        concurrent.futures.wait(futures)
        
    total_time = time.time() - start_all
    print(f"\n--- PRUEBA FINALIZADA EN {total_time:.2f} SEGUNDOS ---")

if __name__ == "__main__":
    main()
