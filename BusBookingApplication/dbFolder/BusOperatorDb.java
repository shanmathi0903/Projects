package dbFolder;

import beanFolder.BusOperator;
import java.util.ArrayList;

import java.io.File;
import java.io.FileWriter;
import java.io.IOException;
import java.io.BufferedWriter;
import java.util.Scanner;
import java.io.FileReader;
import java.io.FileNotFoundException;

public class BusOperatorDb{
	ArrayList<BusOperator> busOperator=new ArrayList<>();
	
	int id=1;
	public void addUserDetails(String name,long phoneNumber,String email,String password){
		busOperator.add(new BusOperator(id,name,phoneNumber,email,password));
		
		try(BufferedWriter bw=new BufferedWriter(new FileWriter("BusOperatorDetails",true))){
			bw.write(id+","+name+","+phoneNumber+","+email+","+password);
			bw.newLine();
		}
		catch(IOException e){
			System.out.println(e.getMessage());
		}
		
		System.out.println("Bus Operator Added Successfully");
		id++;
	}
	
	public boolean checkAccount(String email,String password){
		
		for(int i=0;i<busOperator.size();i++){
			if(email.equals(busOperator.get(i).getEmail()) && password.equals(busOperator.get(i).getPassword())){
				return true;
			}
		}
		return false;
		/*
		boolean found=false;
		try(Scanner scanner=new Scanner(new FileReader("D:/RDE_Training/BusBookingApplication/CustomerDetails"))){
			while(scanner.hasNextLine()){
				String line=scanner.nextLine();
				if(line.contains(email) && line.contains(password)){
					found=true;
					return true;
				}
			}
		}
		catch(FileNotFoundException e){
			System.out.println(e.getMessage());
		}
		
		return found;
		*/
	}
	
	public int getId(String email,String password){
		int id=0;
		for(int i=0;i<busOperator.size();i++){
			if(email.equals(busOperator.get(i).getEmail()) && password.equals(busOperator.get(i).getPassword())){
				id=i;
			}
		}
		return id;
	}
		
	
	public void displayBusOperators(){
		System.out.printf("%-10s %-20s %-15s %-25s%n", "ID", "Name", "Phone Number", "Email");
		System.out.println("---------------------------------------------------------------");
		for(int i=0;i<busOperator.size();i++){
			System.out.printf("%-10s %-20s %-15s %-25s%n",busOperator.get(i).getId(),busOperator.get(i).getName(),busOperator.get(i).getPhoneNumber(),busOperator.get(i).getEmail());
		}
	}

}