import csv 
rows=list(csv.reader(open('NHE2024.csv',newline='',encoding='cp1252'))) 
for i in range(200,360): 
    n=rows[i-1][0] if rows[i-1] else '' 
    print(str(i)+': '+n) 
