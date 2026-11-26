"""Organiza os PSDs (do .rar e os que estavam dentro da pasta do jogo) por tema, sem cópias duplicadas,
e gera miniaturas PNG para visualização no GitHub."""
import os, shutil, hashlib, sys
from PIL import Image
from psd_tools import PSDImage
RAR = "psds_originais/mais novo"; SRC = "psds_from_src"; DST = sys.argv[1]
M = {  # destino : origem
 "interface/layout_principal_2.0.psd": RAR+"/layoutnovo/NOVO TEMPLATE 2.0.psd",
 "interface/layout_principal_2.0_versao_antiga.psd": RAR+"/NOVO TEMPLATE 2.0.psd",
 "interface/layout_estilo_diablo.psd": RAR+"/layoutnovo/novotemplatediablo.psd",
 "interface/titulo_cabecalho.psd": RAR+"/layoutnovo/titulo/titulo.psd",
 "interface/logo_novo.psd": RAR+"/layoutnovo/novologo.psd",
 "interface/botoes_menu.psd": RAR+"/layoutnovo/buttons/button.psd",
 "interface/botoes_viajar_e_comandos.psd": RAR+"/layoutnovo/botoesviajarecomandos.psd",
 "interface/botao_da_cidade.psd": RAR+"/layoutnovo/menuslados/botaodacidade.psd",
 "interface/balao_menu.psd": RAR+"/layoutnovo/balaomenu.psd",
 "interface/barra_de_level.psd": RAR+"/layoutnovo/levelbar.psd",
 "interface/fundo_mensagens.psd": RAR+"/layoutnovo/mailfundo.psd",
 "interface/botao_fechar.psd": RAR+"/layoutnovo/fecharcima.psd",
 "interface/icones_novos.psd": RAR+"/layoutnovo/iconesnovos.psd",
 "interface/menu_meio_topo.psd": RAR+"/layoutnovo/menumeio/cima.psd",
 "interface/menu_meio_laterais.psd": RAR+"/layoutnovo/menumeio/direita esquerda e outros.psd",
 "interface/menu_laterais.psd": RAR+"/layoutnovo/menumeio/menulados.psd",
 "interface/janela_flutuante_topo.psd": RAR+"/layoutnovo/dropmenu/grande/cima.psd",
 "interface/janela_flutuante_completa.psd": RAR+"/layoutnovo/dropmenu/grande/parada full.psd",
 "interface/menu_suspenso.psd": RAR+"/layoutnovo/dropmenu/Untitled-1.psd",
 "interface/botoes_outros_1.psd": RAR+"/layoutnovo/outrosbotoes/NOVO.psd",
 "interface/botoes_outros_2.psd": RAR+"/layoutnovo/outrosbotoes/Sem título-7.psd",
 "interface/layout_sasuke.psd": RAR+"/layoutnovo/sasukelayout.psd",
 "interface/naruto_viajar.psd": RAR+"/narutoviajar.psd",
 "interface/gif_do_site.psd": RAR+"/gifsite.psd",
 "batalha/vitoria_lee.psd": RAR+"/leewin.psd",
 "batalha/sorte_de_batalha.psd": RAR+"/sortedebatalha.psd",
 "batalha/sprites_24px.psd": RAR+"/layoutnovo/graficos/24gif.psd",
 "batalha/cenario_personagem.psd": RAR+"/layoutnovo/graficos/Untitled-14.psd",
 "cartas/monstros.psd": RAR+"/monstros.psd",
 "cartas/carta_teste.psd": RAR+"/layoutnovo/TABLET/carta1.psd",
 "personagens/avatares.psd": SRC+"/layoutnovo/avatares/avatar.psd",
 "personagens/retratos_dos_kages.psd": RAR+"/layoutnovo/personagem/retrato personagem.psd",
 "personagens/falas_dos_personagens.psd": RAR+"/layoutnovo/falapersonagens.psd",
 "personagens/avatares_janela_flutuante.psd": RAR+"/layoutnovo/dropmenu/grande/avatares.psd",
 "personagens/doujutsus.psd": RAR+"/layoutnovo/doujutsus.psd",
 "equipamentos/mochila.psd": RAR+"/layoutnovo/backpack1.psd",
 "equipamentos/slots_da_mochila.psd": RAR+"/layoutnovo/backpackslots2.psd",
 "equipamentos/slots.psd": RAR+"/layoutnovo/backslots.psd",
 "equipamentos/equipamentos_painel.psd": RAR+"/layoutnovo/equipamentos/Sem título-10.psd",
 "equipamentos/icones_equipamentos.psd": RAR+"/layoutnovo/equipamentos/Sem título-11.psd",
 "equipamentos/icones_drops.psd": RAR+"/layoutnovo/equipamentos/drops/Sem título-1.psd",
 "equipamentos/drops_moldura.psd": RAR+"/layoutnovo/equipamentos/drops/dropsemcima.psd",
 "cidades/pergaminho_da_cidade.psd": RAR+"/layoutnovo/cidadepergaminho.psd",
 "cidades/nova_cidade.psd": RAR+"/layoutnovo/novacidade.psd",
 "cidades/konoha.psd": RAR+"/layoutnovo/TABLET/konoha.psd",
 "cidades/konoha_novo.psd": RAR+"/layoutnovo/TABLET/konohanovo.psd",
 "cidades/konoha_pintando.psd": RAR+"/layoutnovo/TABLET/konohapintando.psd",
 "cidades/konoha_novo_pintando.psd": RAR+"/layoutnovo/TABLET/konohanovopintando.psd",
 "cidades/konoha_nova_pintura.psd": RAR+"/layoutnovo/TABLET/newkonohapainting.psd",
 "cidades/montanha_myoboku.psd": RAR+"/layoutnovo/TABLET/montanhamyoboku.psd",
 "cidades/montanha_myoboku_pintando.psd": RAR+"/layoutnovo/TABLET/myobokupintando.psd",
 "cidades/vila_da_nevoa.psd": RAR+"/layoutnovo/TABLET/aguacidade.psd",
 "cidades/vila_da_areia_pintando.psd": RAR+"/layoutnovo/TABLET/sunagakurepintando.psd",
 "cidades/vila_da_nuvem_pintando.psd": RAR+"/layoutnovo/TABLET/raiopntando.psd",
 "cidades/vila_da_pedra_fazendo.psd": RAR+"/layoutnovo/TABLET/terrafazendo - Cópia.psd",
 "cidades/vilarejo_ceu_e_terra.psd": RAR+"/layoutnovo/TABLET/cidadeceueterra.psd",
 "cidades/esconderijo_akatsuki.psd": RAR+"/layoutnovo/TABLET/akatsuki.psd",
 "cidades/castelo_real.psd": RAR+"/layoutnovo/TABLET/castelo.psd",
 "cidades/cidade_vip.psd": RAR+"/layoutnovo/TABLET/cidadepremy.psd",
 "cidades/cidade_generica.psd": RAR+"/layoutnovo/TABLET/cidade.psd",
 "cidades/loja.psd": RAR+"/layoutnovo/TABLET/shop.psd",
 "cidades/loja_pintando.psd": RAR+"/layoutnovo/TABLET/shoppintando.psd",
 "cidades/alta_resolucao/konoha.psd": RAR+"/layoutnovo/TABLET/novas/novakonoha.psd",
 "cidades/alta_resolucao/montanha_myoboku.psd": RAR+"/layoutnovo/TABLET/novas/montanha myoboku.psd",
 "cidades/alta_resolucao/vila_da_pedra.psd": RAR+"/layoutnovo/TABLET/novas/terra - Cópia.psd",
 "cidades/alta_resolucao/vila_da_pedra_rascunho.psd": RAR+"/layoutnovo/TABLET/novas/terra - Cópia - Cópia.psd",
 "interface/mapa_global_grade.psd": RAR+"/Sem título-5.psd",
 "interface/banner_nigeru_rpg.psd": RAR+"/Sem título-6.psd",
 "interface/titulo_lutando.psd": RAR+"/Sem título-7.psd",
}
vistos = {}
for dst, src in M.items():
    h = hashlib.md5(open(src, 'rb').read()).hexdigest()
    if h in vistos: print('duplicado', dst, '=', vistos[h]); continue
    vistos[h] = dst
    p = os.path.join(DST, dst); os.makedirs(os.path.dirname(p), exist_ok=True); shutil.copy2(src, p)
    prev = os.path.join(DST, "previews", dst.replace('/', '__').replace('.psd', '.png'))
    os.makedirs(os.path.dirname(prev), exist_ok=True)
    try:
        psd = PSDImage.open(src); im = psd.composite() if psd.has_preview() else None
        im = (im or psd.composite(force=True)).convert('RGB'); im.thumbnail((480, 480)); im.save(prev)
    except Exception as e: print('sem preview', dst, e)
print('ok', len(vistos))
