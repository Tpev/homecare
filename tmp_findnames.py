import csv 
rows=list(csv.reader(open('NHE2024.csv',newline='',encoding='cp1252'))) 
for i,r in enumerate(rows,1): 
    n=r[0] if r else '' 
    if 'Personal' in n or 'personal' in n or 'Home' in n or 'care' in n.lower(): 
        print(str(i)+': '+n) 
