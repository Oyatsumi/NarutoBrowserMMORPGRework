"""Fix accents that were replaced by '?' in old encoding conversions."""
import re, sys, glob
D = {
'voc?':'você','Voc?':'Você','voc?s':'vocês','est?':'está','n?o':'não','N?o':'Não','miss?o':'missão','Miss?o':'Missão',
's?o':'são','tamb?m':'também','Tamb?m':'Também','fun??o':'função','est?o':'estão','informa??es':'informações','p?gina':'página',
'P?gina':'Página','n?vel':'nível','N?vel':'Nível','h?':'há','H?':'Há','bot?o':'botão','s?':'só','S?':'Só','experi?ncia':'experiência',
'tr?s':'três','poss?vel':'possível','n?veis':'níveis','N?veis':'Níveis','fun??es':'funções','fa?a':'faça','estat?sticas':'estatísticas',
'a??o':'ação','For?a':'Força','for?a':'força','Conclus?o':'Conclusão','n?mero':'número','j?':'já','ilus?o':'ilusão','come?a':'começa',
'varia??o':'variação','ter?':'terá','se??o':'seção','pre?os':'preços','posi??o':'posição','padr?o':'padrão',
'op??o':'opção','miss?es':'missões','m?ximo':'máximo','m?dia':'média','ir?':'irá','invent?rio':'inventário','dispon?veis':'disponíveis',
'diferen?a':'diferença','dif?cil':'difícil','come?ar':'começar','aumentar?':'aumentará','Verifica??o':'Verificação','Introdu??o':'Introdução',
'Especializa??o':'Especialização','Cr?ditos':'Créditos','?ltimo':'último','ver?':'verá','v?rios':'vários','trapa?a':'trapaça','tomar?':'tomará',
't?m':'têm','sistem?tica':'sistemática','separa??o':'separação','revezar?o':'revezarão','receber?':'receberá','r?pida':'rápida',
'programa??o':'programação','preju?zo':'prejuízo','pra?a':'praça','poder?':'poderá','perceber?':'perceberá','pagar?':'pagará',
'op??es':'opções','Op??es':'Opções','obrigat?rias':'obrigatórias','n?s':'nós','multiplica??o':'multiplicação','impede?a':'impeça',
'id?ia':'ideia','Id?ias':'Ideias','hist?rico':'histórico','haver?':'haverá','gastar?':'gastará','f?cticias':'fictícias','explora??o':'exploração',
'especializa??es':'especializações','Especializa??es':'Especializações','espa?os':'espaços','ent?o':'então','dispon?vel':'disponível',
'diferen?as':'diferenças','conte?do':'conteúdo','Conte?do':'Conteúdo','codifica??o':'codificação','caracter?sticas':'características',
'c?digo':'código','C?digo':'Código','bot?es':'botões','b?ssola':'bússola','b?sico':'básico','b?sicas':'básicas','atrav?s':'através','ativa??o':'ativação',
'Ativa??o':'Ativação','at?':'até','al?m':'além','administra??o':'administração','Vin?cius':'Vinícius','Vari?vel':'Variável','R?pido':'Rápido',
'Gradua??o':'Graduação','Come?o':'Começo','Cl?udio':'Cláudio','?s':'às','?reas':'áreas','?nica':'única','?cone':'ícone','formata??o':'formatação',
'Prote??o':'Proteção','prote??o':'proteção','inventáriao':'inventário','F?cil':'Fácil','M?dio':'Médio','Dif?cil':'Difícil',
}
pat = re.compile(r'(?<![A-Za-zÀ-ÿ?])(' + '|'.join(re.escape(k) for k in sorted(D, key=len, reverse=True)) + r')(?![A-Za-zÀ-ÿ?])')
total = 0
for f in sys.argv[1:]:
    t = open(f, encoding='utf8').read()
    n = 0
    def rep(m):
        global n
        return D[m.group(1)]
    new, c = pat.subn(lambda m: D[m.group(1)], t)
    if c:
        open(f, 'w', encoding='utf8', newline='').write(new); total += c
        print(f, c)
print('total', total)
