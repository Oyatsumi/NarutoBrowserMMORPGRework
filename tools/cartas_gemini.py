"""Monta as cartas dos inimigos que eram silhueta a partir dos retratos originais gerados no Gemini (arte/gemini_cartas/p_*.png).
Cada retrato serve à família inteira; as formas avançadas (Selo 1/2, #2/#3, olhar/despertar) ficam mais escuras.
Uso: python3 cartas_gemini.py  -> grava em game/layoutnovo/monstros/<id>.jpg
"""
import os, subprocess, sys, tempfile
from PIL import Image, ImageEnhance

PASTA = 'arte/gemini_cartas'
JOGO = 'game/layoutnovo/monstros'
PSD = 'psds/cartas/monstros.psd'
FAMILIAS = {  # retrato: [(id, nível de escurecimento 0/1/2)]
    'sakon': [(80, 0), ], 'kidoumaru': [(81, 0), ], 'tayuya': [(84, 0), ],
    'agua': [(89, 0)], 'sombras': [(90, 0)], 'sasori': [(104, 0), ], 'kisame': [(131, 0)],
    'itachi': [(129, 0), ],
    'kimimaru': [(91, 0), ], 'deidara': [(108, 0)], 'sasuke': [(146, 0), ], 'kazekage': [(116, 0)],
    'hanzo': [(122, 0)], 'kabuto': [(123, 1)], 'zetsu': [(125, 0)], 'suigetsu': [(135, 0)],
    'juugo': [(136, 0)], 'hidan': [(139, 0)], 'kakuzu': [(140, 0), ], 'orochimaru': [(143, 0)], 'konan': [(144, 0)],
    'gakido': [(109, 0)], 'jigokudo': [(132, 0)], 'shurado': [(145, 0)], 'tendo': [(148, 0)], 'pein': [(149, 0)], 'nagato': [(147, 0)],
    'jiroubou': [(79, 2)],
    'reibi': [(97, 0)], 'ichibi': [(99, 0)], 'nibi': [(101, 0)], 'sanbi': [(103, 0)],
    'yonbi': [(111, 0)], 'gobi': [(114, 0)], 'rokubi': [(128, 0)], 'shichibi': [(133, 0)],
    'hachibi': [(137, 0)], 'cao': [(1, 0)], 'lobogigante': [(50, 0)], 'gamabunta': [(119, 0)], 'manda': [(120, 0)], 'katsuyu': [(121, 0)], 'amaru': [(117, 0)], 'hidanjashin': [(141, 1)], 'izuna': [(150, 0)],
    # Lote de correção (cartas originais com personagens da obra ou imagem errada): p_m<ID>.png
    'm18': [(18, 0), ],
    'm4': [(4, 0)],
    'm5': [(5, 0)],
    'm9': [(9, 0)],
    'm13': [(13, 0)],
    'm15': [(15, 0)],
    'm21': [(21, 0)],
    'm22': [(22, 0)],
    'm23': [(23, 0)],
    'm26': [(26, 0)],
    'm27': [(27, 0)],
    'm30': [(30, 0)],
    'm31': [(31, 0)],
    'm32': [(32, 0)],
    'm36': [(36, 0)],
    'm37': [(37, 0)],
    'm40': [(40, 0)],
    'm41': [(41, 0)],
    'm42': [(42, 0)],
    'm45': [(45, 0)],
    'm46': [(46, 0)],
    'm47': [(47, 0)],
    'm49': [(49, 0)],
    'm51': [(51, 0)],
    'm54': [(54, 0)],
    'm58': [(58, 0)],
    'm59': [(59, 0)],
    'm62': [(62, 0)],
    'm64': [(64, 0)],
    'm67': [(67, 0)],
    'm68': [(68, 0)],
    'm69': [(69, 0)],
    'm70': [(70, 0)],
    'm71': [(71, 0)],
    'm72': [(72, 0)],
    'm74': [(74, 0)],
    'm75': [(75, 0)],
    'm155': [(155, 0)],
    'm158': [(158, 0)],
    'm159': [(159, 0)],
    'm160': [(160, 0)],
 'kyuubi': [(151, 0)], 'kyuubi4': [(126, 0)],
    # Formas avançadas com retrato próprio (nenhuma carta repetida): p_u<ID>.png
    'u87': [(87, 0)],
    'u88': [(88, 0)],
    'u82': [(82, 0)],
    'u83': [(83, 0)],
    'u85': [(85, 0)],
    'u86': [(86, 0)],
    'u92': [(92, 0)],
    'u93': [(93, 0)],
    'u96': [(96, 0)],
    'u98': [(98, 0)],
    'u100': [(100, 0)],
    'u102': [(102, 0)],
    'u110': [(110, 0)],
    'u113': [(113, 0)],
    'u127': [(127, 0)],
    'u105': [(105, 0)],
    'u124': [(124, 0)],
    'u134': [(134, 0)],
    'u106': [(106, 0)],
    'u130': [(130, 0)],
    'u138': [(138, 0)],
    'u112': [(112, 0)],
    'u142': [(142, 0)],
    'u11': [(11, 0)],
    # Inimigos novos 222-235 (imunidades): p_n<ID>.png
    'n222': [(222, 0)],
    'n223': [(223, 0)],
    'n224': [(224, 0)],
    'n225': [(225, 0)],
    'n226': [(226, 0)],
    'n227': [(227, 0)],
    'n228': [(228, 0)],
    'n229': [(229, 0)],
    'n230': [(230, 0)],
    'n231': [(231, 0)],
    'n232': [(232, 0)],
    'n233': [(233, 0)],
    'n234': [(234, 0)],
    'n235': [(235, 0)],
    # Inimigos novos 236-250 (tools/inimigos_novos2.py): p_n<ID>.png
    'n236': [(236, 0)],
    'n237': [(237, 0)],
    'n238': [(238, 0)],
    'n239': [(239, 0)],
    'n240': [(240, 0)],
    'n241': [(241, 0)],
    'n242': [(242, 0)],
    'n243': [(243, 0)],
    'n244': [(244, 0)],
    'n245': [(245, 0)],
    'n246': [(246, 0)],
    'n247': [(247, 0)],
    'n248': [(248, 0)],
    'n249': [(249, 0)],
    'n250': [(250, 0)],

}

def main(somente=None):
    tmp = tempfile.mkdtemp()
    n = 0
    for retrato, ids in FAMILIAS.items():
        if somente and retrato not in somente: continue
        arq = os.path.join(PASTA, 'p_%s.png' % retrato)
        if not os.path.exists(arq): continue
        base = Image.open(arq).convert('RGB')
        for idm, esc in ids:
            ImageEnhance.Brightness(base).enhance((1.0, 0.86, 0.72)[esc]).save(os.path.join(tmp, '%d.png' % idm)); n += 1
    out = tempfile.mkdtemp()
    subprocess.check_call([sys.executable, os.path.join(os.path.dirname(os.path.abspath(__file__)), 'estilizar_cartas.py'), tmp, PSD, out])
    for f in os.listdir(out):
        if f.endswith('.jpg'): os.replace(os.path.join(out, f), os.path.join(JOGO, f))
    print(n, 'cartas')

if __name__ == '__main__':
    main(sys.argv[1:] or None)
