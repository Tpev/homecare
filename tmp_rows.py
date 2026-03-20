import csv 
rows=list(csv.reader(open('NHE2024.csv',newline='',encoding='cp1252'))) 
for i in range(360,430): 
    r=rows[i-1] 
    print(str(i)+': '+r[0]+' ; 2024='+r[-1]) 
