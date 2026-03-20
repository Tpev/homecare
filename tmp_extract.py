import csv 
with open('NHE2024.csv', newline='', encoding='cp1252') as f: 
    rows=list(csv.reader(f)) 
start=220; end=255 
for i in range(start-1,end): 
    row=rows[i] 
    name=row[0] if row else '' 
    val=row[-1] if row else '' 
    print(str(i+1)+': '+name+' - 2024='+val) 
