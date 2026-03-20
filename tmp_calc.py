total=169365 
vals={'Out_of_pocket':30368,'Private_health_insurance':37291,'Medicare':55742,'Medicaid':38189,'CHIP':83,'VA':1610,'Other_third_party':6083} 
for k,v in vals.items(): 
    print(k,v,round(v/total*100,1)) 
